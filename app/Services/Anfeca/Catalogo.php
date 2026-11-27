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

    /** Clave interna (CORREO, TEL_PARTICULAR, TEL_CELULAR, CODIGO_POSTAL, COLONIA, MUNICIPIO) → TICO_ID. */
    public static function tipoComunicacion(string $clave): int
    {
        $descripcion = (string) config("anfeca_8088.tipos_comunicacion.{$clave}");

        return self::recordar("tico:{$clave}", fn () => self::obligatorio(
            DB::table('tipo_comunicacion')->where('tico_tipo_comunicacion', $descripcion)->value('tico_id_tipo_comunicacion'),
            'TIPO_COMUNICACION', $descripcion
        ));
    }

    /** @return array<int, string> TICO_ID → clave interna */
    public static function tiposComunicacionPorId(): array
    {
        return self::recordar('tico:todos', function () {
            $porDescripcion = array_flip((array) config('anfeca_8088.tipos_comunicacion'));

            return DB::table('tipo_comunicacion')->get()->mapWithKeys(fn ($t) => [
                (int) $t->tico_id_tipo_comunicacion => $porDescripcion[$t->tico_tipo_comunicacion] ?? null,
            ])->filter()->all();
        });
    }

    /** Normaliza para comparar: minúsculas, sin acentos ni signos, un espacio. */
    public static function clave(?string $texto): string
    {
        return \Illuminate\Support\Str::of((string) $texto)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value();
    }

    /** Nombre oficial de la entidad a partir del texto capturado, o null si no se reconoce. */
    public static function nombreEntidad(?string $texto): ?string
    {
        $clave = self::clave($texto);
        foreach ((array) config('anfeca_8088.entidades_federativas') as $nombre) {
            if (self::clave($nombre) === $clave) {
                return $nombre;
            }
        }

        return config('anfeca_8088.alias_entidades.'.$clave);
    }

    public static function entidadPorNombre(string $nombre): ?int
    {
        $nombre = self::nombreEntidad($nombre) ?? $nombre;
        return self::recordar("enfe:{$nombre}", fn () => DB::table('entidad_federativa')
            ->where('enfe_nombre', $nombre)->value('enfe_id_entidad_federativa'));
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

        return self::recordar("docu:{$campo}", fn () => self::obligatorio(
            DB::table('documento')->where('docu_descripcion', $descripcion)->value('docu_id_documento'),
            'DOCUMENTO', $descripcion
        ));
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

        return self::recordar("esdo:{$clave}", fn () => self::obligatorio(
            DB::table('estado_documento')->where('esdo_estado_documento', $descripcion)->value('esdo_id_estado_documento'),
            'ESTADO_DOCUMENTO', $descripcion
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
