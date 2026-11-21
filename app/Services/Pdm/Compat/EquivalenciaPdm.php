<?php

namespace App\Services\Pdm\Compat;

use App\Services\Pdm\NormalizadorCatalogos;
use Illuminate\Support\Facades\DB;

/**
 * SOLO LECTURA. Verifica que lo que Laravel leería en modo principal/exclusivo (vistas
 * anfeca_compat sobre el PDM) equivale a lo que lee hoy de public, fila por fila y columna
 * por columna.
 *
 * Normalizaciones DOCUMENTADAS (no son diferencias):
 *  - '' y nulo son equivalentes (Laravel convierte cadenas vacías en nulo).
 *  - entidad_federativa: el PDM guarda el nombre oficial INEGI (N-1/N-2): «CDMX» → «Ciudad de México».
 *  - genero: mismo valor sin distinguir mayúsculas ni acentos.
 *  - títulos fuera del catálogo: el PDM los registra en revisión y la vista los devuelve como
 *    «Otro» + texto en *_otro (la captura original se conserva íntegra).
 *  - solicitudes sin tipo de personal o área: la vista devuelve el valor de la selección (N-6).
 *  - historial: la vista puede tener MÁS filas que public (versiones que solo conserva el PDM)
 *    y sus IDs son los del PDM; se compara por (solicitud, tipo, ruta).
 *  - instituciones_afiliadas: numero_afiliacion proviene de la decisión D-11 y no hay fechas en el PDM.
 *  - instituciones_afiliadas.escuela_facultad (N-7): opcional en Laravel, INST_FACULTAD obligatoria en el PDM.
 *    Si public la tiene vacía, el PDM conserva la facultad confirmada en D-11 y la vista la devuelve;
 *    se acepta solo si la vista trae un valor. Si public tiene valor, la comparación es exacta.
 *  - certificados_historicos.user_id: nulo si el usuario no tiene USUARIO en el PDM (D-1 A).
 */
class EquivalenciaPdm
{
    public const TABLAS = [
        'datos_solicitantes', 'selecciones_certificacion', 'solicitudes_certificacion', 'datos_refrendo',
        'formaciones_academicas_solicitud', 'documentos_probatorios_solicitud', 'historial_documentos_academicos',
        'certificados_historicos', 'instituciones_afiliadas',
    ];

    /** Columnas que no se comparan (ver normalizaciones). */
    private const OMITIR = [
        'instituciones_afiliadas' => ['created_at', 'updated_at', 'numero_afiliacion', 'sede', 'zona_anfeca'],
        'certificados_historicos' => ['user_id'],
        'historial_documentos_academicos' => ['id', 'created_at', 'updated_at'],
    ];

    /** Versiones del historial que solo existen en el PDM (informativo). */
    public int $historialAdicional = 0;

    private NormalizadorCatalogos $normalizador;

    /** @var array<string, true> títulos ACT del catálogo por "nivel|titulo" */
    private array $titulosActivos = [];

    public function __construct()
    {
        $this->normalizador = new NormalizadorCatalogos();
    }

    /** @return list<array{tabla:string, tipo:string, clave:string, detalle:string}> */
    public function verificar(): array
    {
        $this->titulosActivos = [];
        foreach (DB::select("select n.nipr_nivel_profesional as nivel, e.espr_nombre as titulo from anfeca_modelo.estudio_profesional e
                join anfeca_modelo.nivel_profesional n on n.nipr_id_nivel_profesional = e.espr_id_nivel_profesional where e.espr_estado = 'ACT'") as $t) {
            $this->titulosActivos[$t->nivel.'|'.$t->titulo] = true;
        }

        $diferencias = [];
        $this->historialAdicional = 0;

        foreach (self::TABLAS as $tabla) {
            $clave = $tabla === 'historial_documentos_academicos' ? ['solicitud_id', 'tipo_documento', 'ruta_archivo'] : ['id'];
            $publicas = $this->indexar(DB::select("select * from public.{$tabla}"), $clave, $tabla, 'public', $diferencias);
            $vistas = $this->indexar(DB::select("select * from anfeca_compat.{$tabla}"), $clave, $tabla, 'vista', $diferencias);

            foreach ($publicas as $k => $p) {
                if (!isset($vistas[$k])) {
                    $diferencias[] = $this->dif($tabla, 'FALTANTE', $k, 'Fila de public que la vista no devuelve.');
                    continue;
                }
                $p = $this->normalizar($tabla, $p, $vistas[$k]);
                foreach ($p as $col => $valor) {
                    if (in_array($col, self::OMITIR[$tabla] ?? [], true)) {
                        continue;
                    }
                    $real = $vistas[$k][$col] ?? null;
                    if ($this->valor($valor) !== $this->valor($real)) {
                        $diferencias[] = $this->dif($tabla, 'DISTINTA', $k, "{$col}: public ".$this->mostrar($valor).', vista '.$this->mostrar($real).'.', $col);
                    }
                }
            }

            foreach (array_keys($vistas) as $k) {
                if (isset($publicas[$k])) {
                    continue;
                }
                if ($tabla === 'historial_documentos_academicos') {
                    $this->historialAdicional++;
                    continue;
                }
                $diferencias[] = $this->dif($tabla, 'SOBRANTE', $k, 'Fila de la vista que no existe en public.');
            }
        }

        return $diferencias;
    }

    /** Aplica a la fila de public las normalizaciones documentadas. */
    private function normalizar(string $tabla, array $p, array $v): array
    {
        if ($tabla === 'datos_solicitantes') {
            $entidad = $this->normalizador->entidadFederativa($p['entidad_federativa'] ?? null);
            if (!$entidad->revision) {
                $p['entidad_federativa'] = $entidad->valor;
            }
            if (NormalizadorCatalogos::clave($p['genero'] ?? null) === NormalizadorCatalogos::clave($v['genero'] ?? null)) {
                $p['genero'] = $v['genero'];
            }
        }

        if ($tabla === 'formaciones_academicas_solicitud') {
            foreach (['origen' => ['nivel_origen', 'titulo_origen', 'titulo_origen_otro'],
                      'ultimo' => ['nivel_ultimo_grado', 'titulo_ultimo_grado', 'titulo_ultimo_grado_otro']] as [$nivel, $titulo, $otro]) {
                $t = $p[$titulo] ?? null;
                if ($t === null || $t === '') {
                    continue;
                }
                if ($t === 'Otro') {
                    $texto = trim((string) ($p[$otro] ?? ''));
                    $p[$otro] = $texto === '' ? null : $texto;
                } elseif (isset($this->titulosActivos[($p[$nivel] ?? '').'|'.$t])) {
                    $p[$otro] = null;
                } else {
                    // Título histórico fuera del catálogo → «Otro» + texto (registrado en revisión).
                    $p[$titulo] = 'Otro';
                    $p[$otro] = $t;
                }
            }
        }

        if ($tabla === 'instituciones_afiliadas'
            && trim((string) ($p['escuela_facultad'] ?? '')) === ''
            && trim((string) ($v['escuela_facultad'] ?? '')) !== '') {
            $p['escuela_facultad'] = $v['escuela_facultad']; // N-7
        }

        if ($tabla === 'solicitudes_certificacion') {
            foreach (['tipo_personal', 'area_certificacion'] as $col) {
                if (($p[$col] ?? null) === null || trim((string) $p[$col]) === '') {
                    $p[$col] = $v[$col] ?? null; // N-6
                }
            }
        }

        return $p;
    }

    private function indexar(array $filas, array $clave, string $tabla, string $origen, array &$diferencias): array
    {
        $indice = [];
        foreach ($filas as $f) {
            $f = (array) $f;
            $k = implode('|', array_map(fn ($c) => (string) $this->valor($f[$c] ?? null), $clave));
            if (isset($indice[$k]) && $tabla !== 'historial_documentos_academicos') {
                $diferencias[] = $this->dif($tabla, 'DUPLICADA', $k, "Clave repetida en {$origen}.");
            }
            $indice[$k] = $f;
        }

        return $indice;
    }

    private function valor(mixed $v): ?string
    {
        return match (true) {
            $v === null, $v === '' => null,
            is_bool($v) => $v ? '1' : '0',
            default => (string) $v,
        };
    }

    private function mostrar(mixed $v): string
    {
        $t = $this->valor($v);

        return $t === null ? 'nulo' : (mb_strlen($t) > 40 ? '«'.mb_substr($t, 0, 12).'…» ('.mb_strlen($t).' car.)' : "«{$t}»");
    }

    /** @return array{tabla:string, tipo:string, clave:string, detalle:string, columna:?string} */
    private function dif(string $tabla, string $tipo, string $clave, string $detalle, ?string $columna = null): array
    {
        return compact('tabla', 'tipo', 'clave', 'detalle', 'columna');
    }
}
