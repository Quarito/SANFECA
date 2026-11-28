<?php

namespace App\Services\Anfeca;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Catálogos del modelo oficial (36 tablas). Resuelve identificadores por descripción y conserva en
 * memoria lo ya consultado durante la petición. Las descripciones viven en config/anfeca_8088.php.
 *
 * Los catálogos se cargan con «php artisan anfeca:catalogos» (solo datos; nunca crea estructuras).
 */
final class Catalogo
{
    /** @var array<string, mixed> */
    private static array $cache = [];

    public static function olvidar(): void
    {
        self::$cache = [];
    }

    private static function recordar(string $clave, callable $consulta): mixed
    {
        if (!array_key_exists($clave, self::$cache)) {
            self::$cache[$clave] = $consulta();
        }

        return self::$cache[$clave];
    }

    private static function obligatorio(?int $id, string $catalogo, string $valor): int
    {
        if ($id === null) {
            throw new RuntimeException("Catálogo {$catalogo} sin el valor «{$valor}». Ejecute php artisan anfeca:catalogos.");
        }

        return $id;
    }

    // ---------------------------------------------------------------- comunicaciones y domicilio

    /** @return list<string> descripción vigente seguida de sus equivalentes aceptados */
    public static function descripcionesComunicacion(string $clave): array
    {
        $vigente = config("anfeca_8088.tipos_comunicacion.{$clave}");
        if ($vigente === null) {
            throw new RuntimeException("Medio de contacto desconocido: {$clave}.");
        }

        return array_values(array_unique(array_merge([(string) $vigente], (array) config("anfeca_8088.tipos_comunicacion_equivalentes.{$clave}", []))));
    }

    /** @return list<int> TICO_ID existentes para la clave, el de la descripción vigente primero */
    public static function tiposComunicacion(string $clave): array
    {
        return self::recordar("tico*:{$clave}", function () use ($clave) {
            $ids = [];
            foreach (self::descripcionesComunicacion($clave) as $descripcion) {
                foreach (DB::table('tipo_comunicacion')->where('tico_tipo_comunicacion', $descripcion)->orderBy('tico_id_tipo_comunicacion')
                    ->pluck('tico_id_tipo_comunicacion') as $id) {
                    $ids[] = (int) $id;
                }
            }

            return array_values(array_unique($ids));
        });
    }

    /** Clave interna de medio de contacto (CORREO, TEL_PARTICULAR, TEL_CELULAR) → TICO_ID existente (vigente o equivalente). */
    public static function tipoComunicacion(string $clave): int
    {
        return self::obligatorio(self::tiposComunicacion($clave)[0] ?? null, 'TIPO_COMUNICACION', implode(' / ', self::descripcionesComunicacion($clave)));
    }

    /** @return array<int, string> TICO_ID → clave interna (incluye las descripciones equivalentes) */
    public static function tiposComunicacionPorId(): array
    {
        return self::recordar('tico:todos', function () {
            $mapa = [];
            foreach (array_keys((array) config('anfeca_8088.tipos_comunicacion')) as $clave) {
                foreach (self::tiposComunicacion($clave) as $id) {
                    $mapa[$id] = $clave;
                }
            }

            return $mapa;
        });
    }

    /** Normaliza para comparar: minúsculas, sin acentos ni signos, un espacio. */
    public static function clave(?string $texto): string
    {
        return \Illuminate\Support\Str::of((string) $texto)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value();
    }

    // ---------------------------------------------------------------- solicitud

    public static function area(string $descripcion): ?int
    {
        return self::recordar("area:{$descripcion}", fn () => DB::table('area_certificacion')
            ->where('area_descripcion', $descripcion)->value('area_id_area_certificacion'));
    }

    public static function tipoPersonal(string $descripcion): ?int
    {
        return self::recordar("tipa:{$descripcion}", fn () => DB::table('tipo_personal_academico')
            ->where('tipa_descripcion', $descripcion)->where('tipa_estado', true)->value('tipa_id_tipo_personal_academico'));
    }

    public static function articulo(string $subindice): ?int
    {
        return self::recordar("arce:{$subindice}", fn () => DB::table('articulo_subindice')
            ->where('arce_subindice', $subindice)->value('arce_id_articulo'));
    }

    /**
     * Convocatoria vigente de la certificación (ANFECA, CACSLA o CACECA = TIPO_CONVOCATORIA.TICO_SIGLAS):
     * la de registro abierto más reciente; si ninguna está abierta, la más reciente de esa certificación.
     */
    public static function convocatoria(string $certificacion): int
    {
        return self::recordar("conv:{$certificacion}", function () use ($certificacion) {
            $base = DB::table('convocatoria as c')
                ->join('tipo_convocatoria as t', 't.tico_id_tipo_convocatoria', '=', 'c.conv_id_tipo_convocatoria')
                ->where('t.tico_siglas', $certificacion)->where('t.tico_estado', 'ACT');
            $hoy = now('America/Mexico_City')->toDateString();
            $id = (clone $base)->where('c.conv_fecha_inicio_registro', '<=', $hoy)->where('c.conv_fecha_fin_registro', '>=', $hoy)
                ->orderByDesc('c.conv_fecha_inicio_registro')->value('c.conv_id_convocatoria')
                ?? (clone $base)->orderByDesc('c.conv_fecha_inicio_registro')->value('c.conv_id_convocatoria');

            return self::obligatorio($id, 'CONVOCATORIA', $certificacion);
        });
    }

    /** Siglas (BO, RF, FA, DP, CA, EN) → [id de C_ESTADO_SOLICITUD, descripción]. */
    public static function estadoSolicitud(string $siglas): array
    {
        $descripcion = (string) config("anfeca_8088.estados_solicitud.{$siglas}");

        return self::recordar("ceso:{$siglas}", fn () => [self::obligatorio(
            DB::table('c_estado_solicitud')->where('ceso_estado_solicitud', $descripcion)->value('ceso_id_c_estado_solicitud'),
            'C_ESTADO_SOLICITUD', $descripcion
        ), $descripcion]);
    }

    // ---------------------------------------------------------------- documentos

    /** Campo de la aplicación (p. ej. constancia_antiguedad_archivo) → DOCU_ID. */
    public static function documento(string $campo): int
    {
        $descripcion = (string) config("anfeca_8088.documentos.{$campo}");

        return self::recordar("docu:{$campo}", fn () => self::documentalActivo(
            'documento', 'docu_id_documento', 'docu_descripcion', 'docu_estatus', $descripcion
        ));
    }

    private static function documentalActivo(string $tabla, string $id, string $campo, string $estado, string $descripcion): int
    {
        $ids = DB::table($tabla)->where($campo, $descripcion)->where($estado, 'ACT')->limit(2)->pluck($id);
        if ($ids->count() > 1) {
            throw new RuntimeException("Catálogo {$tabla} con más de una fila activa para «{$descripcion}».");
        }

        return self::obligatorio($ids->first(), strtoupper($tabla), $descripcion);
    }

    /** @return array<int, string> DOCU_ID → campo */
    public static function documentosPorId(): array
    {
        return self::recordar('docu:todos', function () {
            $campos = array_flip((array) config('anfeca_8088.documentos'));

            return DB::table('documento')->get()->mapWithKeys(fn ($d) => [
                (int) $d->docu_id_documento => $campos[$d->docu_descripcion] ?? null,
            ])->filter()->all();
        });
    }

    /** Clave (CARGADO, SUSTITUIDO, VALIDADO, CON_OBSERVACIONES, NO_VALIDO) → ESDO_ID. */
    public static function estadoDocumento(string $clave): int
    {
        $descripcion = (string) config("anfeca_8088.estados_documento.{$clave}");

        return self::recordar("esdo:{$clave}", fn () => self::documentalActivo(
            'estado_documento', 'esdo_id_estado_documento', 'esdo_estado_documento', 'esdo_estado', $descripcion
        ));
    }

    /** @return array<int, string> ESDO_ID → clave */
    public static function estadosDocumentoPorId(): array
    {
        return self::recordar('esdo:todos', function () {
            $claves = array_flip((array) config('anfeca_8088.estados_documento'));

            return DB::table('estado_documento')->get()->mapWithKeys(fn ($d) => [
                (int) $d->esdo_id_estado_documento => $claves[$d->esdo_estado_documento] ?? null,
            ])->filter()->all();
        });
    }

    // ---------------------------------------------------------------- formación

    public static function nivel(string $nivel): int
    {
        return self::recordar("nipr:{$nivel}", fn () => self::obligatorio(
            DB::table('nivel_profesional')->where('nipr_nivel_profesional', $nivel)->value('nipr_id_nivel_profesional'),
            'NIVEL_PROFESIONAL', $nivel
        ));
    }

    // ---------------------------------------------------------------- roles

    public static function rol(string $nombre): int
    {
        return self::recordar("rol:{$nombre}", fn () => self::obligatorio(
            DB::table('rol')->where('rol_nombre', $nombre)->value('rol_id_rol'),
            'ROL', $nombre
        ));
    }
}
