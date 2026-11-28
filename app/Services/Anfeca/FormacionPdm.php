<?php

namespace App\Services\Anfeca;

use App\Services\Catalogos\CatalogoLicenciaturas;
use App\Exceptions\Catalogos\CatalogoNoDisponible;
use App\Services\Catalogos\CatalogoPosgrados;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Formación académica sobre NIVEL_PROFESIONAL, ESTUDIO_PROFESIONAL y PERSONA_ESTUDIO_PROFESIONAL.
 *
 * Cada persona tiene a lo sumo dos estudios vigentes: la licenciatura de ORIGEN y el ÚLTIMO grado.
 * Las filas importadas ACT conservan su estado. El nivel determina el papel de un estudio ACT.
 * Se siguen leyendo las filas Laravel anteriores cuyo código de tres caracteres representa:
 *   1.º origen del nombre: G categoría general · C catálogo ANUIES · M captura manual · O «Otro» (texto libre)
 *   2.º categoría del título: A Administración · C Contaduría · I Informática · N Negocios Internacionales · O Otro
 *   3.º papel del estudio: L licenciatura de origen · U último grado
 * La institución de afiliación es PERSONA.PERS_ID_INSTITUCION. La autorización es el evento «Formación académica
 * autorizada» (ESTADO_SOLICITUD) de la solicitud. Títulos y cédulas: PEES_URL_TITULO / PEES_URL_CEDULA, y cada
 * carga queda además como SOLICITUD_DOCUMENTO de la solicitud (historial).
 */
final class FormacionPdm
{
    public function __construct(private readonly DocumentosPdm $documentos)
    {
    }

    private const ROLES = ['origen' => 'L', 'ultimo' => 'U'];

    /** Fila PEES vigente del papel indicado (origen o último) de la persona. */
    private function estudio(int $persona, string $papel): ?object
    {
        return DB::table('persona_estudio_profesional as p')
            ->join('estudio_profesional as e', 'e.espr_id_estudio_profesional', '=', 'p.pees_id_estuido_profesional')
            ->join('nivel_profesional as n', 'n.nipr_id_nivel_profesional', '=', 'e.espr_id_nivel_profesional')
            ->where('p.pees_id_persona', $persona)
            ->where(function ($q) use ($papel) {
                $q->whereRaw("e.espr_estado ~ '^[GCMO][ACINO][LU]$' and substr(e.espr_estado, 3, 1) = ?", [self::ROLES[$papel]])
                    ->orWhere(fn ($activo) => $activo->where('e.espr_estado', 'ACT')
                        ->whereIn('n.nipr_nivel_profesional', $papel === 'origen'
                            ? ['Licenciatura'] : ['Especialidad', 'Maestría', 'Doctorado']));
            })
            ->orderByDesc('p.pees_id_persona_estudio_profesi')
            ->first();
    }

    /**
     * Formación con los nombres de campo de las pantallas (formaciones_academicas_solicitud), o null si la persona
     * aún no la registra.
     */
    public function cargar(int $persona, ?int $solicitud): ?Fluent
    {
        $origen = $this->estudio($persona, 'origen');
        $ultimo = $this->estudio($persona, 'ultimo');
        if (!$origen && !$ultimo) {
            return null;
        }
        $datos = ['solicitud_id' => $solicitud, 'persona_id' => $persona];
        $datos += $this->decodificar($origen, 'origen');
        $datos += $this->decodificar($ultimo, 'ultimo_grado');
        $datos['nivel_origen'] = $origen?->nipr_nivel_profesional;
        $datos['institucion_afiliada_id'] = DB::table('persona')->where('pers_id_persona', $persona)->value('pers_id_institucion');
        $datos['titulo_origen_archivo'] = $origen?->pees_url_titulo;
        $datos['cedula_origen_archivo'] = $origen?->pees_url_cedula;
        $datos['titulo_ultimo_grado_archivo'] = $ultimo?->pees_url_titulo;
        $datos['cedula_ultimo_grado_archivo'] = $ultimo?->pees_url_cedula;
        // Declaración de posgrado: con licenciatura registrada, hay posgrado vigente o no.
        $datos['cuenta_con_posgrado'] = $origen
            ? ($ultimo !== null && in_array($ultimo->nipr_nivel_profesional, ['Especialidad', 'Maestría', 'Doctorado'], true))
            : null;
        if (!config('anfeca_catalogos.declaracion_posgrado.activo')) {
            // Formulario anterior (sin casilla): la declaración no se registró explícitamente.
            $datos['cuenta_con_posgrado'] = null;
        }
        $datos['formacion_completada_at'] = $solicitud ? app(Tramites::class)->momentoEvento($solicitud, 'FA') : null;

        return new Fluent($datos);
    }

    /** @return array<string, mixed> columnas de un papel */
    private function decodificar(?object $fila, string $sufijo): array
    {
        $vacio = ["titulo_{$sufijo}" => null, "titulo_{$sufijo}_otro" => null, "titulo_{$sufijo}_especifico" => null, "origen_titulo_{$sufijo}" => null];
        $vacio[$sufijo === 'origen' ? 'clave_licenciatura_catalogo' : 'clave_posgrado_catalogo'] = null;
        $vacio[$sufijo === 'origen' ? 'version_catalogo_licenciaturas' : 'version_catalogo_posgrados'] = null;
        if ($sufijo === 'ultimo_grado') {
            $vacio['nivel_ultimo_grado'] = null;
        }
        if (!$fila) {
            return $vacio;
        }
        $categoria = config('anfeca_8088.categorias_titulo.'.substr($fila->espr_estado, 1, 1));
        $nombre = $fila->espr_nombre;
        $datos = $vacio;
        if ($sufijo === 'ultimo_grado') {
            $datos['nivel_ultimo_grado'] = $fila->nipr_nivel_profesional;
        }
        if ($fila->espr_estado === 'ACT') {
            $coincidencia = $this->tituloActivo($nombre, $fila->nipr_nivel_profesional);
            $datos["titulo_{$sufijo}_especifico"] = $nombre;
            if ($coincidencia !== null) {
                $datos["titulo_{$sufijo}"] = $coincidencia['area'];
                $datos["titulo_{$sufijo}_otro"] = $coincidencia['area'] === 'Otro' ? $nombre : null;
                $datos["origen_titulo_{$sufijo}"] = 'catalogo';
                $datos[$sufijo === 'origen' ? 'clave_licenciatura_catalogo' : 'clave_posgrado_catalogo'] = $coincidencia['clave'];
                $datos[$sufijo === 'origen' ? 'version_catalogo_licenciaturas' : 'version_catalogo_posgrados'] = $coincidencia['version'];
            }

            return $datos;
        }

        switch ($fila->espr_estado[0]) {
            case 'G':
                $datos["titulo_{$sufijo}"] = $nombre;
                break;
            case 'O':
                $datos["titulo_{$sufijo}"] = 'Otro';
                $datos["titulo_{$sufijo}_otro"] = $nombre;
                break;
            default:
                $datos["titulo_{$sufijo}"] = $categoria;
                $datos["titulo_{$sufijo}_otro"] = $categoria === 'Otro' ? mb_substr($nombre, 0, 200) : null;
                $datos["titulo_{$sufijo}_especifico"] = $nombre;
                $datos["origen_titulo_{$sufijo}"] = $fila->espr_estado[0] === 'C' ? 'catalogo' : 'manual';
                if ($fila->espr_estado[0] === 'C') {
                    [$clave, $version] = $this->claveCatalogo($sufijo === 'origen', $nombre, $fila->nipr_nivel_profesional, $categoria);
                    $datos[$sufijo === 'origen' ? 'clave_licenciatura_catalogo' : 'clave_posgrado_catalogo'] = $clave;
                    $datos[$sufijo === 'origen' ? 'version_catalogo_licenciaturas' : 'version_catalogo_posgrados'] = $version;
                }
        }

        return $datos;
    }

    /** Clave y versión del catálogo ANUIES para un título registrado (el modelo guarda solo el nombre). */
    private function claveCatalogo(bool $licenciatura, string $titulo, string $nivel, ?string $area): array
    {
        try {
            $catalogo = app($licenciatura ? CatalogoLicenciaturas::class : CatalogoPosgrados::class);
            foreach ($catalogo->datos()['titulos'] ?? [] as $t) {
                if (($t['titulo'] ?? null) === $titulo && ($licenciatura || (($t['nivel'] ?? null) === $nivel && ($t['area'] ?? null) === $area))) {
                    return [$t['clave'], $catalogo->version()];
                }
            }
        } catch (Throwable) {
            // Catálogo no disponible: el título se conserva; solo falta la clave para preseleccionarlo.
        }

        return [null, null];
    }

    /** Codifica un papel a partir de las columnas validadas por el controlador. @return array{0:string,1:string,2:string} nombre, nivel, estado */
    private function codificar(array $d, string $sufijo, string $nivel): ?array
    {
        $titulo = $d["titulo_{$sufijo}"] ?? null;
        if (blank($titulo)) {
            return null;
        }
        $letra = array_search($titulo, (array) config('anfeca_8088.categorias_titulo'), true);
        if ($letra === false) {
            throw ValidationException::withMessages(["titulo_{$sufijo}" => 'El título seleccionado no es válido.']);
        }
        $especifico = trim((string) ($d["titulo_{$sufijo}_especifico"] ?? ''));
        $origen = $d["origen_titulo_{$sufijo}"] ?? null;
        $otro = trim((string) ($d["titulo_{$sufijo}_otro"] ?? ''));
        if ($especifico !== '' && in_array($origen, ['catalogo', 'manual'], true)) {
            [$nombre, $tipo] = [$especifico, $origen === 'catalogo' ? 'C' : 'M'];
        } elseif ($titulo === 'Otro') {
            if ($otro === '') {
                throw ValidationException::withMessages(["titulo_{$sufijo}_otro" => 'Escriba el nombre del título.']);
            }
            [$nombre, $tipo] = [$otro, 'O'];
        } else {
            [$nombre, $tipo] = [$titulo, 'G'];
        }
        if (mb_strlen($nombre) > 150) {
            $campo = $sufijo === 'origen' ? 'titulo_origen_manual' : 'titulo_ultimo_grado_manual';
            throw ValidationException::withMessages([$tipo === 'O' ? "titulo_{$sufijo}_otro" : $campo =>
                'El nombre del título no debe exceder 150 caracteres (límite de ESTUDIO_PROFESIONAL.ESPR_NOMBRE).']);
        }

        return [$nombre, $nivel, $tipo.$letra.self::ROLES[$sufijo === 'origen' ? 'origen' : 'ultimo']];
    }

    private function tituloActivo(string $nombre, string $nivel): ?array
    {
        $licenciatura = $nivel === 'Licenciatura';
        try {
            $catalogo = app($licenciatura ? CatalogoLicenciaturas::class : CatalogoPosgrados::class);
            $coincidencias = [];
            foreach ($catalogo->datos()['titulos'] as $titulo) {
                if (($licenciatura || $titulo['nivel'] === $nivel)
                    && mb_strtolower(trim($titulo['titulo'])) === mb_strtolower(trim($nombre))) {
                    $coincidencias[] = $titulo + ['version' => $catalogo->version()];
                }
            }

            return count($coincidencias) === 1 ? $coincidencias[0] : null;
        } catch (CatalogoNoDisponible) {
            return null;
        }
    }

    private function estudioProfesional(string $nombre, string $nivel, string $estado, string $sufijo, array $datos): int
    {
        $nivelId = Catalogo::nivel($nivel);
        $existentes = DB::table('estudio_profesional')->where('espr_id_nivel_profesional', $nivelId);
        // La comparación Unicode de títulos no depende del locale del clúster PostgreSQL.
        $activos = (clone $existentes)->where('espr_estado', 'ACT')
            ->get(['espr_id_estudio_profesional', 'espr_nombre'])
            ->filter(fn ($fila) => mb_strtolower(trim($fila->espr_nombre)) === mb_strtolower(trim($nombre)));
        if ($activos->isNotEmpty()) {
            $titulo = $this->tituloActivo($activos->first()->espr_nombre, $nivel);
            $clave = $sufijo === 'origen' ? 'clave_licenciatura_catalogo' : 'clave_posgrado_catalogo';
            if ($activos->count() !== 1 || $titulo === null || $estado[0] !== 'C'
                || ($sufijo !== 'origen' && $nivel === 'Licenciatura')
                || $titulo['clave'] !== ($datos[$clave] ?? null)
                || $titulo['area'] !== ($datos["titulo_{$sufijo}"] ?? null)) {
                throw ValidationException::withMessages(["titulo_{$sufijo}" =>
                    'El estudio ya existe como activo. Seleccione su coincidencia inequívoca en el catálogo para conservar su clasificación.']);
            }

            return (int) $activos->first()->espr_id_estudio_profesional;
        }

        $id = $existentes->where('espr_nombre', $nombre)->where('espr_estado', $estado)->value('espr_id_estudio_profesional');

        return $id ?? DB::table('estudio_profesional')->insertGetId([
            'espr_id_nivel_profesional' => $nivelId, 'espr_nombre' => $nombre, 'espr_estado' => $estado,
        ], 'espr_id_estudio_profesional');
    }

    /**
     * Guarda la formación (dentro de la transacción del controlador).
     *
     * @param array<string, mixed> $datos columnas validadas (nombres de las pantallas)
     * @param array<string, UploadedFile> $archivos
     * @param bool $sinPosgrado escenario «solo licenciatura»: se retira el último grado vigente
     */
    public function guardar(int $persona, int $solicitud, array $datos, array $archivos, bool $sinPosgrado): void
    {
        $this->documentos->verificarCatalogo(array_keys($archivos));
        $actual = $this->cargar($persona, $solicitud);
        $completo = array_merge($actual?->toArray() ?? [], $datos);

        $papeles = ['origen' => ['origen', 'Licenciatura']];
        if (!$sinPosgrado) {
            $papeles['ultimo'] = ['ultimo_grado', (string) ($completo['nivel_ultimo_grado'] ?? '')];
        }
        $estudios = [];
        foreach ($papeles as $papel => [$sufijo, $nivel]) {
            $codigo = $nivel === '' ? null : $this->codificar($completo, $sufijo, $nivel);
            if ($codigo !== null) {
                [$nombre, $nivelEstudio, $estado] = $codigo;
                $estudios[$papel] = $this->estudioProfesional($nombre, $nivelEstudio, $estado, $sufijo, $completo);
            } elseif (isset($archivos["titulo_{$sufijo}_archivo"]) || isset($archivos["cedula_{$sufijo}_archivo"])) {
                throw ValidationException::withMessages(["titulo_{$sufijo}" => 'Seleccione un estudio identificable antes de adjuntar sus documentos.']);
            }
        }
        foreach ($estudios as $papel => $espr) {
            $fila = $this->estudio($persona, $papel);
            $campoTitulo = $papel === 'origen' ? 'titulo_origen_archivo' : 'titulo_ultimo_grado_archivo';
            $campoCedula = $papel === 'origen' ? 'cedula_origen_archivo' : 'cedula_ultimo_grado_archivo';
            $urlTitulo = isset($archivos[$campoTitulo]) ? $this->documentos->guardar($solicitud, $campoTitulo, $archivos[$campoTitulo]) : $fila?->pees_url_titulo;
            $urlCedula = isset($archivos[$campoCedula]) ? $this->documentos->guardar($solicitud, $campoCedula, $archivos[$campoCedula]) : $fila?->pees_url_cedula;
            if ($fila && (int) $fila->pees_id_estuido_profesional === $espr) {
                DB::table('persona_estudio_profesional')->where('pees_id_persona_estudio_profesi', $fila->pees_id_persona_estudio_profesi)
                    ->update(['pees_url_titulo' => $urlTitulo, 'pees_url_cedula' => $urlCedula]);
            } else {
                if ($fila) {
                    // Cambio de estudio: la fila anterior se retira (sus archivos permanecen en SOLICITUD_DOCUMENTO).
                    $this->retirar($fila);
                }
                DB::table('persona_estudio_profesional')->insert([
                    'pees_id_estuido_profesional' => $espr, 'pees_id_persona' => $persona,
                    'pees_url_titulo' => $urlTitulo, 'pees_cedula' => null, 'pees_url_cedula' => $urlCedula,
                ]);
            }
        }
        if ($sinPosgrado && ($fila = $this->estudio($persona, 'ultimo'))) {
            $this->retirar($fila);
        }
        if (filled($datos['institucion_afiliada_id'] ?? null)) {
            app(ExpedienteSolicitante::class)->asignarInstitucion($persona, (int) $datos['institucion_afiliada_id']);
        }
    }

    private function retirar(object $fila): void
    {
        DB::table('persona_estudio_profesional')->where('pees_id_persona_estudio_profesi', $fila->pees_id_persona_estudio_profesi)
            ->where('pees_id_estuido_profesional', $fila->pees_id_estuido_profesional)->where('pees_id_persona', $fila->pees_id_persona)->delete();
    }
}
