<?php

namespace App\Console\Commands;

use App\Services\Anfeca\Catalogo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga (idempotente) los valores de catálogo que la aplicación necesita dentro de las 36 tablas oficiales.
 * Solo INSERTA filas faltantes: no crea, altera ni elimina estructuras, y no modifica filas existentes.
 */
class CargarCatalogosAnfeca extends Command
{
    protected $signature = 'anfeca:catalogos {--convocatoria-anio= : Año de la convocatoria a registrar si no existe}';

    protected $description = 'Carga los catálogos de ANFECA en las 36 tablas oficiales (solo datos).';

    private int $insertadas = 0;

    public function handle(): int
    {
        DB::transaction(function () {
            // ENTIDAD_FEDERATIVA, ALCALDIA_MUNICIPIO y COLONIA se cargan del catálogo oficial con anfeca:sepomex.

            foreach ((array) config('anfeca_8088.tipos_comunicacion') as $descripcion) {
                $this->asegurar('tipo_comunicacion', ['tico_tipo_comunicacion' => $descripcion], ['tico_estado' => 'ACT']);
            }
            foreach ((array) config('anfeca_8088.estados_solicitud') as $descripcion) {
                $this->asegurar('c_estado_solicitud', ['ceso_estado_solicitud' => $descripcion]);
            }
            foreach ((array) config('anfeca_8088.documentos') as $descripcion) {
                $this->asegurar('documento', ['docu_descripcion' => $descripcion], ['docu_estatus' => 'ACT']);
            }
            foreach ((array) config('anfeca_8088.estados_documento') as $descripcion) {
                $this->asegurar('estado_documento', ['esdo_estado_documento' => $descripcion], ['esdo_estado' => 'ACT']);
            }
            foreach ((array) config('anfeca_8088.niveles_profesionales') as $nivel) {
                $this->asegurar('nivel_profesional', ['nipr_nivel_profesional' => $nivel]);
            }
            foreach ((array) config('anfeca_8088.areas_certificacion') as $area) {
                $this->asegurar('area_certificacion', ['area_descripcion' => $area]);
            }
            foreach ((array) config('anfeca_8088.tipos_personal_academico') as $tipo) {
                $this->asegurar('tipo_personal_academico', ['tipa_descripcion' => $tipo], ['tipa_estado' => true]);
            }
            foreach ((array) config('anfeca_8088.articulos_subindice') as $subindice => $descripcion) {
                $this->asegurar('articulo_subindice', ['arce_subindice' => (string) $subindice], ['arce_descripcion' => $descripcion]);
            }
            foreach ((array) config('anfeca_8088.certificaciones') as $siglas => $descripcion) {
                $this->asegurar('tipo_convocatoria', ['tico_siglas' => $siglas], ['tico_tipo_convocatoria' => $descripcion, 'tico_estado' => 'ACT']);
            }
            foreach ((array) config('anfeca_8088.privilegios') as $nombre => $descripcion) {
                $this->asegurar('privilegio', ['priv_nombre' => $nombre], ['priv_descripcion' => $descripcion]);
            }
            foreach ((array) config('anfeca_8088.roles') as $rol => $privilegios) {
                $this->asegurar('rol', ['rol_nombre' => $rol]);
                $rolId = DB::table('rol')->where('rol_nombre', $rol)->value('rol_id_rol');
                foreach ($privilegios as $p) {
                    $this->asegurar('rol_privilegio', ['ropr_id_rol' => $rolId,
                        'ropr_id_privilegio' => DB::table('privilegio')->where('priv_nombre', $p)->value('priv_id_privilegio')]);
                }
            }
            if ($anio = $this->option('convocatoria-anio')) {
                $this->convocatorias((int) $anio);
            }
        });
        Catalogo::olvidar();
        $this->info("Catálogos verificados. Filas insertadas: {$this->insertadas}.");
        $sinConvocatoria = DB::table('tipo_convocatoria as t')->whereNotExists(fn ($q) => $q->from('convocatoria as c')
            ->whereColumn('c.conv_id_tipo_convocatoria', 't.tico_id_tipo_convocatoria'))->pluck('tico_siglas')->all();
        if ($sinConvocatoria) {
            $this->warn('Sin convocatoria registrada: '.implode(', ', $sinConvocatoria).'. Registre las convocatorias oficiales '
                .'(o use --convocatoria-anio solo en laboratorio) antes de recibir solicitudes.');
        }

        return self::SUCCESS;
    }

    /** Convocatorias de laboratorio: fechas del año indicado; montos y puntuaciones deben sustituirse por los oficiales. */
    private function convocatorias(int $anio): void
    {
        foreach (DB::table('tipo_convocatoria')->get() as $t) {
            $this->asegurar('convocatoria', [
                'conv_id_tipo_convocatoria' => $t->tico_id_tipo_convocatoria,
                'conv_nombre' => "Convocatoria {$t->tico_siglas} {$anio}",
            ], [
                'conv_monto_recuperacion' => '0.00',
                'conv_fecha_inicio_registro' => "{$anio}-01-01",
                'conv_fecha_fin_registro' => "{$anio}-12-31",
                'conv_fecha_resultado' => "{$anio}-12-31",
                'conv_fecha_entrega_certificados' => "{$anio}-12-31",
                'conv_puntuacion_max' => 100,
                'conv_puntuacion_min' => 0,
                'conv_lugar_entrega' => null,
                'conv_evento_entrega' => null,
            ]);
        }
    }

    private function asegurar(string $tabla, array $clave, array $resto = []): void
    {
        if (!DB::table($tabla)->where($clave)->exists()) {
            DB::table($tabla)->insert($clave + $resto);
            $this->insertadas++;
        }
    }
}
