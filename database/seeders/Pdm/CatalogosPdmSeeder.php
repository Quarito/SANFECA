<?php

namespace Database\Seeders\Pdm;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Catálogos del PDM a partir de las fuentes reales del sistema (config/anfeca_pdm.php,
 * que transcribe las listas de los componentes Vue y controladores).
 *
 * - Idempotente: busca por la clave estable de cada catálogo; no duplica ni borra.
 * - No forma parte de DatabaseSeeder: se ejecuta de forma explícita
 *   (php artisan db:seed --class="Database\Seeders\Pdm\CatalogosPdmSeeder").
 * - Requiere PostgreSQL con anfeca_modelo y sus extensiones.
 */
class CatalogosPdmSeeder extends Seeder
{
    private const E = 'anfeca_modelo.';

    public function run(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql'
            || !DB::selectOne("select to_regclass('anfeca_modelo.zona') as t")->t) {
            throw new RuntimeException('Requiere PostgreSQL con anfeca_modelo y sus extensiones instaladas.');
        }

        $c = config('anfeca_pdm.catalogos');

        DB::transaction(function () use ($c) {
            // Entidades: ID = clave INEGI.
            foreach ($c['entidades_federativas'] as $id => $nombre) {
                $this->asegurar('entidad_federativa', ['enfe_nombre' => $nombre], ['enfe_id_entidad_federativa' => $id]);
            }

            foreach ($c['tipos_comunicacion'] as $descripcion) {
                $this->asegurar('tipo_comunicacion', ['tico_tipo_comunicacion' => $descripcion], ['tico_estado' => 'ACT']);
            }

            foreach ($c['estados_solicitud'] as $descripcion) {
                $this->asegurar('c_estado_solicitud', ['ceso_estado_solicitud' => $descripcion]);
            }

            foreach ($c['documentos'] as $descripcion) {
                $this->asegurar('documento', ['docu_descripcion' => $descripcion], ['docu_estatus' => 'ACT']);
            }

            foreach ($c['estados_documento'] as $descripcion) {
                $this->asegurar('estado_documento', ['esdo_estado_documento' => $descripcion], ['esdo_estado' => 'ACT']);
            }

            foreach ($c['articulos_subindice'] as $subindice => $descripcion) {
                $this->asegurar('articulo_subindice', ['arce_subindice' => $subindice], ['arce_descripcion' => $descripcion]);
            }

            foreach ($c['areas_certificacion'] as $descripcion) {
                $this->asegurar('area_certificacion', ['area_descripcion' => $descripcion]);
            }

            foreach ($c['tipos_personal_academico'] as $descripcion) {
                $this->asegurar('tipo_personal_academico', ['tipa_descripcion' => $descripcion], ['tipa_estado' => true]);
            }

            foreach ($c['niveles_profesionales'] as $nivel) {
                $idNivel = $this->asegurar('nivel_profesional', ['nipr_nivel_profesional' => $nivel]);

                // Títulos del formulario para cada nivel (sin "Otro": ese se registra como REV al capturarse).
                foreach ($c['titulos'] as $titulo) {
                    $this->asegurar(
                        'estudio_profesional',
                        ['espr_id_nivel_profesional' => $idNivel, 'espr_nombre' => $titulo],
                        ['espr_estado' => 'ACT']
                    );
                }
            }

            // Las entidades se insertan con ID explícito: alinear la secuencia.
            DB::statement("select setval(pg_get_serial_sequence('anfeca_modelo.entidad_federativa', 'enfe_id_entidad_federativa'),
                (select max(enfe_id_entidad_federativa) from anfeca_modelo.entidad_federativa))");
        });
    }

    /**
     * Inserta la fila si no existe la clave; nunca modifica una fila existente.
     * Devuelve el ID de la fila.
     */
    private function asegurar(string $tabla, array $clave, array $resto = []): int
    {
        $pk = DB::selectOne(
            "select a.attname as pk from pg_index i
             join pg_attribute a on a.attrelid = i.indrelid and a.attnum = any(i.indkey)
             where i.indrelid = ?::regclass and i.indisprimary limit 1",
            [self::E.$tabla]
        )->pk;

        $existente = DB::table(self::E.$tabla)->where($clave)->value($pk);

        if ($existente !== null) {
            return (int) $existente;
        }

        return (int) DB::table(self::E.$tabla)->insertGetId(array_merge($clave, $resto), $pk);
    }
}
