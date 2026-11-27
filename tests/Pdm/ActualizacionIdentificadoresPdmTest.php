<?php

namespace Tests\Pdm;

use Illuminate\Support\Facades\DB;

class ActualizacionIdentificadoresPdmTest extends PdmTestCase
{
    use ExpedienteFicticio;

    public function test_conserva_datos_y_repara_compatibilidad_en_un_esquema_anterior(): void
    {
        ['persona' => $persona, 'solicitud' => $solicitud] = $this->expediente();
        $contacto = $persona->comunicaciones()->firstOrFail();
        $documento = $solicitud->solicitudDocumentos()->firstOrFail();
        $vistaAntes = DB::table('anfeca_compat.historial_documentos_academicos')->get()->all();

        DB::unprepared(<<<'SQL'
            alter table anfeca_modelo.comunicacion rename column comu_id_persona to cumu_id_persona;
            alter table anfeca_modelo.convocatoria rename column conv_lugar_entrega to con_lugar_entrega;
            alter table anfeca_modelo.convocatoria rename column conv_evento_entrega to con_evento_entrega;
            alter table anfeca_modelo.solicitud rename column soli_art13 to solo_art13;
            alter table anfeca_modelo.solicitud_documento rename column socu_id_documento to docu_id_documento;

            create or replace function anfeca_compat.comunicacion(p_persona int, p_campo text)
            returns varchar language sql stable as $$
                select c.comu_valor from anfeca_modelo.comunicacion c
                join anfeca_modelo.tipo_comunicacion t on t.tico_id_tipo_comunicacion = c.comu_id_tipo_comunicacion
                join anfeca_compat.mapa_comunicacion m on m.descripcion = t.tico_tipo_comunicacion
                where c.cumu_id_persona = p_persona and m.campo = p_campo limit 1
            $$;
            SQL);

        $migracion = require database_path('migrations_pdm/2026_11_20_000001_alinear_identificadores_powerdesigner.php');
        $migracion->up();
        $migracion->up();

        $this->assertSame($contacto->getAttributes(), $contacto->fresh()->getAttributes());
        $this->assertSame($documento->getAttributes(), $documento->fresh()->getAttributes());
        $this->assertSame($solicitud->soli_art13, $solicitud->fresh()->soli_art13);
        $this->assertEquals($vistaAntes, DB::table('anfeca_compat.historial_documentos_academicos')->get()->all());

        $id = $persona->pers_id_persona;
        DB::selectOne("select anfeca_compat.comunicacion_set(?, 'telefono_celular', '5512345678')", [$id]);
        $this->assertSame('5512345678', DB::selectOne("select anfeca_compat.comunicacion(?, 'telefono_celular') as telefono", [$id])->telefono);
    }

    public function test_rechaza_columnas_ambiguas_antes_de_renombrar(): void
    {
        DB::statement('alter table anfeca_modelo.solicitud rename column soli_art13 to solo_art13');
        DB::statement('alter table anfeca_modelo.solicitud_documento add column docu_id_documento integer');

        $migracion = require database_path('migrations_pdm/2026_11_20_000001_alinear_identificadores_powerdesigner.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Estado ambiguo en anfeca_modelo.solicitud_documento');

        try {
            $migracion->up();
        } finally {
            $this->assertTrue(DB::getSchemaBuilder()->hasColumn('anfeca_modelo.solicitud', 'solo_art13'));
        }
    }
}
