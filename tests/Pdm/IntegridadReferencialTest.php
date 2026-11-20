<?php

namespace Tests\Pdm;

use App\Models\Pdm\AlcaldiaMunicipio;
use App\Models\Pdm\Colonia;
use App\Models\Pdm\Convocatoria;
use App\Models\Pdm\CoordinadorZona;
use App\Models\Pdm\Persona;
use App\Models\Pdm\Privilegio;
use App\Models\Pdm\RolPrivilegio;
use App\Models\Pdm\TipoConvocatoria;
use App\Models\Pdm\Zona;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Las 47 llaves foráneas rechazan registros huérfanos y borrados en cascada.
 */
class IntegridadReferencialTest extends PdmTestCase
{
    use ExpedienteFicticio;

    /** Pone al menos una fila en cada tabla hija de las 47 FK. */
    private function poblarTodo(): void
    {
        ['solicitud' => $s, 'usuario' => $u] = $this->expediente();
        $this->etapa2($s);

        $municipio = AlcaldiaMunicipio::create(['almu_id_entidad_federativa' => 9, 'almu_nombre' => 'Alcaldía ficticia']);
        Colonia::create(['colo_id_alcaldia' => $municipio->almu_id_alcaldia, 'colo_nombre' => 'Colonia ficticia', 'colo_codigo_postal' => '06000']);

        $tipo = TipoConvocatoria::create(['tico_tipo_convocatoria' => 'Ficticia', 'tico_estado' => 'ACT', 'tico_siglas' => 'FIC']);
        $convocatoria = Convocatoria::create([
            'conv_id_tipo_convocatoria' => $tipo->tico_id_tipo_convocatoria, 'conv_nombre' => 'Convocatoria ficticia',
            'conv_monto_recuperacion' => '0', 'conv_fecha_inicio_registro' => '2026-01-01', 'conv_fecha_fin_registro' => '2026-02-01',
            'conv_fecha_resultado' => '2026-03-01', 'conv_fecha_entrega_certificados' => '2026-04-01',
            'conv_puntuacion_max' => 100, 'conv_puntuacion_min' => 60,
        ]);
        $s->update(['soli_id_convocatoria' => $convocatoria->conv_id_convocatoria]);

        $privilegio = Privilegio::create(['priv_nombre' => 'ficticio', 'priv_descripcion' => 'Privilegio ficticio']);
        RolPrivilegio::create(['ropr_id_rol' => $u->usua_id_rol, 'ropr_id_privilegio' => $privilegio->priv_id_privilegio]);

        CoordinadorZona::create(['cozo_id_zona' => Zona::firstOrFail()->zona_id_zona, 'cozo_id_usuario' => $u->usua_id_usuario]);

        // Extensiones F4.
        DB::table('anfeca_modelo.seleccion_certificacion')->insert(['secc_id_usuario' => $u->usua_id_usuario, 'secc_certificacion' => 'ANFECA',
            'secc_tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'secc_area_certificacion' => 'Administración']);
        DB::table('anfeca_modelo.solicitud_refrendo')->insert(['sore_id_solicitud' => $s->soli_id_solicitud, 'sore_refrendo_actual' => 2]);
        DB::table('anfeca_modelo.formacion_solicitud')->insert(['fosc_id_solicitud' => $s->soli_id_solicitud,
            'fosc_id_institucion' => DB::table('anfeca_modelo.institucion')->value('inst_id_institucion')]);
        DB::table('anfeca_modelo.documentos_solicitud')->insert(['dosc_id_solicitud' => $s->soli_id_solicitud]);
    }

    public function test_cada_una_de_las_47_fk_rechaza_un_huerfano(): void
    {
        $this->poblarTodo();

        $fks = DB::select("
            select k.conname as nombre, c.relname as tabla, a.attname as columna
            from pg_constraint k
            join pg_class c on c.oid = k.conrelid
            join pg_attribute a on a.attrelid = k.conrelid and a.attnum = k.conkey[1]
            where k.contype = 'f' and k.connamespace = 'anfeca_modelo'::regnamespace
            order by 1");

        $this->assertCount(47, $fks);

        foreach ($fks as $fk) {
            $this->assertTrue(
                DB::table("anfeca_modelo.{$fk->tabla}")->exists(),
                "Sin fila de prueba en {$fk->tabla} para {$fk->nombre}"
            );

            try {
                DB::transaction(function () use ($fk) {
                    DB::statement(
                        "update anfeca_modelo.{$fk->tabla} set {$fk->columna} = -1
                         where ctid = (select ctid from anfeca_modelo.{$fk->tabla} limit 1)"
                    );
                });
                $this->fail("{$fk->nombre}: aceptó un valor huérfano en {$fk->tabla}.{$fk->columna}");
            } catch (QueryException $e) {
                $this->assertSame('23503', $e->getCode(), "{$fk->nombre}: error inesperado {$e->getMessage()}");
                $this->assertStringContainsString(strtolower($fk->nombre), strtolower($e->getMessage()));
            }
        }
    }

    public function test_todas_las_fk_son_restrict_y_no_borran_en_cascada(): void
    {
        $acciones = DB::select("
            select conname, confdeltype, confupdtype from pg_constraint
            where contype = 'f' and connamespace = 'anfeca_modelo'::regnamespace");

        foreach ($acciones as $a) {
            $this->assertSame('r', $a->confdeltype, "{$a->conname} on delete");
            $this->assertSame('r', $a->confupdtype, "{$a->conname} on update");
        }
    }

    public function test_no_se_puede_borrar_una_persona_con_expediente(): void
    {
        ['persona' => $p] = $this->expediente();

        $this->expectException(QueryException::class);
        Persona::whereKey($p->pers_id_persona)->delete();
    }
}
