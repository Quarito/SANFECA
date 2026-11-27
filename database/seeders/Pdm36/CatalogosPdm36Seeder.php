<?php

namespace Database\Seeders\Pdm36;

use App\Services\Pdm36\EsquemaPdm36;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Catálogos del modelo de 36 tablas, tomados de config('anfeca_pdm.catalogos') (los mismos del sistema actual).
 * Solo inserta lo que falta (idempotente) y únicamente en tablas del modelo aprobado.
 * Roles: solicitante, coordinador_nacional y coordinador_zona_1…7 (la zona del coordinador es su rol).
 */
class CatalogosPdm36Seeder extends Seeder
{
    private const E = EsquemaPdm36::ESQUEMA.'.';

    public const ROLES = ['solicitante', 'coordinador_nacional', 'coordinador_zona_1', 'coordinador_zona_2', 'coordinador_zona_3',
        'coordinador_zona_4', 'coordinador_zona_5', 'coordinador_zona_6', 'coordinador_zona_7'];

    public function run(): void
    {
        if (EsquemaPdm36::inventario() !== EsquemaPdm36::ESPERADO || EsquemaPdm36::tablasAjenas() !== 0) {
            throw new RuntimeException('La base no contiene exactamente el modelo aprobado de 36 tablas.');
        }
        $c = config('anfeca_pdm.catalogos');
        DB::transaction(function () use ($c) {
            foreach ($c['entidades_federativas'] as $id => $nombre) {
                $this->asegurar('entidad_federativa', ['enfe_id_entidad_federativa' => $id], ['enfe_nombre' => $nombre], 'enfe_id_entidad_federativa');
            }
            DB::statement("select setval(pg_get_serial_sequence('".self::E."entidad_federativa', 'enfe_id_entidad_federativa'), (select max(enfe_id_entidad_federativa) from ".self::E.'entidad_federativa))');
            foreach ($c['tipos_comunicacion'] as $d) {
                $this->asegurar('tipo_comunicacion', ['tico_tipo_comunicacion' => $d], ['tico_estado' => 'ACT'], 'tico_id_tipo_comunicacion');
            }
            foreach ($c['estados_solicitud'] as $d) {
                $this->asegurar('c_estado_solicitud', ['ceso_estado_solicitud' => $d], [], 'ceso_id_c_estado_solicitud');
            }
            foreach ($c['documentos'] as $d) {
                $this->asegurar('documento', ['docu_descripcion' => $d], ['docu_estatus' => 'ACT'], 'docu_id_documento');
            }
            foreach ($c['estados_documento'] as $d) {
                $this->asegurar('estado_documento', ['esdo_estado_documento' => $d], ['esdo_estado' => 'ACT'], 'esdo_id_estado_documento');
            }
            foreach ($c['articulos_subindice'] as $sub => $d) {
                $this->asegurar('articulo_subindice', ['arce_subindice' => $sub], ['arce_descripcion' => $d], 'arce_id_articulo');
            }
            foreach ($c['areas_certificacion'] as $d) {
                $this->asegurar('area_certificacion', ['area_descripcion' => $d], [], 'area_id_area_certificacion');
            }
            foreach ($c['tipos_personal_academico'] as $d) {
                $this->asegurar('tipo_personal_academico', ['tipa_descripcion' => $d], ['tipa_estado' => true], 'tipa_id_tipo_personal_academico');
            }
            foreach ($c['niveles_profesionales'] as $d) {
                $this->asegurar('nivel_profesional', ['nipr_nivel_profesional' => $d], [], 'nipr_id_nivel_profesional');
            }
            foreach (self::ROLES as $r) {
                $this->asegurar('rol', ['rol_nombre' => $r], [], 'rol_id_rol');
            }
        });
    }

    private function asegurar(string $tabla, array $clave, array $resto, string $pk): int
    {
        $existente = DB::table(self::E.$tabla)->where($clave)->value($pk);

        return $existente !== null ? (int) $existente : (int) DB::table(self::E.$tabla)->insertGetId(array_merge($clave, $resto), $pk);
    }
}
