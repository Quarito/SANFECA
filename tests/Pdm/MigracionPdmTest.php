<?php

namespace Tests\Pdm;

use App\Services\Pdm\Migracion\PlanMigracionPdm;
use App\Services\Pdm\Migracion\ProteccionMigracion;
use Illuminate\Support\Facades\DB;

/**
 * F2 · Migración public → anfeca_modelo y conciliación, sobre PostgreSQL aislado.
 */
class MigracionPdmTest extends PdmTestCase
{
    use DatosPublicFicticios;

    private string $informe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->informe = tempnam(sys_get_temp_dir(), 'informe_pdm_');
        config(['anfeca_pdm_decisiones' => ['instituciones' => [], 'domicilios' => [], 'certificados' => []]]);
        $this->crearPublicFicticio();
    }

    protected function tearDown(): void
    {
        @unlink($this->informe);
        parent::tearDown();
    }

    private function migrar(array $opciones = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('anfeca:pdm-migrar', $opciones + ['--informe' => $this->informe]);
    }

    private function cuenta(string $tabla, array $donde = []): int
    {
        return DB::table("anfeca_modelo.{$tabla}")->where($donde)->count();
    }

    private function codigos(bool $bloqueantes): array
    {
        $informe = json_decode((string) file_get_contents($this->informe), true);

        return collect($informe['incidencias'])->where('bloqueante', $bloqueantes)
            ->map(fn ($i) => $i['codigo'].'#'.$i['id'])->sort()->values()->all();
    }

    public function test_el_plan_es_de_solo_lectura_y_reporta_los_bloqueos(): void
    {
        $huella = ProteccionMigracion::huellaPublic();

        $this->migrar()->assertExitCode(0);

        $this->assertSame(0, $this->cuenta('persona'));
        $this->assertSame(0, $this->cuenta('solicitud'));
        $this->assertSame($huella, ProteccionMigracion::huellaPublic());

        $this->assertSame([
            'CERTIFICADO_EN_REVISION#2',          // D-14
            'ENTIDAD_EN_REVISION#2',              // D-13 Monterrey
            'ENTIDAD_EN_REVISION#3',              // D-13 Entidad de Prueba
            'INSTITUCION_SIN_DATOS_OFICIALES#1',  // D-11
        ], $this->codigos(true));

        $info = $this->codigos(false);
        $this->assertContains('USUARIO_SIN_DATOS_PERSONALES#5', $info);
        $this->assertContains('USUARIO_SIN_DATOS_PERSONALES#6', $info);
        $this->assertContains('N6_DESDE_SELECCION#2', $info);
        $this->assertContains('ROL_DESDE_USERS#', $info);
        $this->assertContains('PERSONA_SIN_INSTITUCION#1', $info);
    }

    public function test_ejecutar_se_niega_mientras_haya_bloqueos(): void
    {
        $this->migrar(['--ejecutar' => true, '--confirmar' => true])
            ->expectsOutputToContain('incidencia(s) bloqueante(s)')
            ->assertExitCode(1);

        $this->assertSame(0, $this->cuenta('persona'));
    }

    public function test_la_simulacion_escribe_concilia_y_revierte(): void
    {
        config(['anfeca_pdm_decisiones' => $this->decisionesCompletas()]);
        $huella = ProteccionMigracion::huellaPublic();

        $this->migrar(['--simular' => true])
            ->expectsOutputToContain('Simulación correcta')
            ->assertExitCode(0);

        foreach (['persona', 'usuario', 'solicitud', 'solicitud_documento', 'historico_certificado_importacion', 'rol'] as $tabla) {
            $this->assertSame(0, $this->cuenta($tabla), "{$tabla} debe quedar vacía tras la simulación");
        }
        $this->assertSame($huella, ProteccionMigracion::huellaPublic());
    }

    public function test_migracion_completa_conserva_datos_relaciones_e_identificadores(): void
    {
        config(['anfeca_pdm_decisiones' => $this->decisionesCompletas()]);
        $huella = ProteccionMigracion::huellaPublic();

        $this->migrar(['--ejecutar' => true, '--confirmar' => true])
            ->expectsOutputToContain('Migración confirmada')
            ->assertExitCode(0);

        // public no cambió.
        $this->assertSame($huella, ProteccionMigracion::huellaPublic());

        // Conteos y ausencia de duplicados.
        $this->assertSame(4, $this->cuenta('persona'));
        $this->assertSame(4, $this->cuenta('usuario'), 'users 5 y 6 sin datos no generan USUARIO');
        $this->assertSame(3, $this->cuenta('domicilio'), 'persona 3: sin domicilio por decisión');
        $this->assertSame(8, $this->cuenta('comunicacion'), 'celular + correo de 4 personas');
        $this->assertSame(3, $this->cuenta('solicitud'));
        $this->assertSame(3, $this->cuenta('historico_certificado_importacion'), 'las 3 filas, incluida la de revisión');
        $this->assertSame(3, $this->cuenta('historico_certificado'), 'h1: 2 eventos, h3: 1, h2: ninguno');
        $this->assertSame(2, $this->cuenta('rol'));
        $this->assertSame(1, $this->cuenta('institucion'));
        $this->assertSame(1, $this->cuenta('sede'));
        $this->assertSame(1, $this->cuenta('coordinador_zona'));

        // IDs conservados y folio = id.
        $this->assertEqualsCanonicalizing([1, 2, 3, 4], DB::table('anfeca_modelo.persona')->pluck('pers_id_persona')->all());
        $this->assertSame([1, 2, 3], DB::table('anfeca_modelo.solicitud')->orderBy('soli_id_solicitud')->pluck('soli_folio_solicitud')->all());

        // Normalización de entidades y género.
        $entidad = fn (int $p) => DB::table('anfeca_modelo.domicilio')->where('domi_id_persona', $p)->value('domi_id_entidad_federativa');
        $this->assertSame(9, $entidad(1), 'cdmx → Ciudad de México');
        $this->assertSame(19, $entidad(2), 'Monterrey → Nuevo León solo por decisión registrada');
        $this->assertSame(23, $entidad(4), 'quintana roo → Quintana Roo');
        $this->assertNull(DB::table('anfeca_modelo.persona')->where('pers_id_persona', 4)->value('pers_genero'));
        $this->assertSame('Colonia Ficticia', DB::table('anfeca_modelo.domicilio')->where('domi_id_persona', 1)->value('domi_colonia'));

        // Refrendo art. 14 con área desde la selección (N-6) y antecedente.
        $s2 = DB::table('anfeca_modelo.solicitud')->where('soli_id_solicitud', 2)->first();
        $this->assertSame('refrendo', $s2->soli_tipo_tramite);
        $this->assertTrue($s2->soli_art14);
        $this->assertFalse($s2->solo_art13);
        $this->assertSame('14.1', DB::table('anfeca_modelo.articulo_subindice')->where('arce_id_articulo', $s2->soli_id_articulo)->value('arce_subindice'));
        $this->assertSame('Informática Administrativa', DB::table('anfeca_modelo.area_certificacion')->where('area_id_area_certificacion', $s2->soli_id_area_certificacion)->value('area_descripcion'));
        $this->assertSame('1000022', $s2->soli_numero_certificado);
        $this->assertSame(1, (int) $s2->soli_id_historico_importacion);

        // Eventos de la solicitud enviada, en hora de la Ciudad de México.
        $eventos = DB::table('anfeca_modelo.estado_solicitud')->where('esso_id_solicitud', 1)->orderBy('esso_fecha')->orderBy('esso_hora')
            ->get(['esso_siglas', 'esso_fecha', 'esso_hora'])->map(fn ($e) => "{$e->esso_siglas} {$e->esso_fecha} {$e->esso_hora}")->all();
        $this->assertSame(['BO 2026-11-10 12:00:00', 'FA 2026-11-11 11:15:00', 'CA 2026-11-12 14:00:00', 'EN 2026-11-12 14:30:00'], $eventos);
        $this->assertSame(['BO'], DB::table('anfeca_modelo.estado_solicitud')->where('esso_id_solicitud', 3)->pluck('esso_siglas')->all());

        // Formación con «Otro» en revisión.
        $ultimo = DB::table('anfeca_modelo.persona_estudio_profesional as p')
            ->join('anfeca_modelo.estudio_profesional as e', 'e.espr_id_estudio_profesional', '=', 'p.pees_id_estuido_profesional')
            ->where('p.pees_id_solicitud', 1)->where('p.pees_tipo', 'ULTIMO')->first();
        $this->assertSame('Finanzas corporativas', $ultimo->espr_nombre);
        $this->assertSame('REV', $ultimo->espr_estado);
        $this->assertSame('anfeca/documentos/solicitudes/1/titulo_ultimo_v2.pdf', $ultimo->pees_url_titulo);

        // Documentos: 7 vigentes + 1 sustituido (historial), con sus rutas intactas; pago.
        $docs = DB::table('anfeca_modelo.solicitud_documento as d')
            ->join('anfeca_modelo.estado_solicitud_documento as s', 's.esdo_id_documentacion', '=', 'd.socu_id_documentacion')
            ->join('anfeca_modelo.estado_documento as e', 'e.esdo_id_estado_documento', '=', 's.esdo_id_estado_documento')
            ->where('d.socu_id_solicitud', 1)->get(['d.socu_url_archivo', 'e.esdo_estado_documento']);
        $this->assertSame(7, $docs->where('esdo_estado_documento', 'Cargado')->count());
        $this->assertSame(['anfeca/documentos/solicitudes/1/titulo_ultimo_v1.pdf'], $docs->where('esdo_estado_documento', 'Sustituido')->pluck('socu_url_archivo')->values()->all());
        $this->assertContains('https://example.test/evidencias', $docs->pluck('socu_url_archivo')->all());
        $this->assertSame('anfeca/documentos/solicitudes/1/pago.pdf', DB::table('anfeca_modelo.pago')->where('pago_id_solicitud', 1)->value('pago_url_comprobante'));

        // Certificados: copia fiel y estado de normalización.
        $this->assertSame('ANF-77', DB::table('anfeca_modelo.historico_certificado_importacion')->where('hcim_id_importacion', 2)->value('hcim_numero_certificado'));
        $this->assertSame('REVISION', DB::table('anfeca_modelo.historico_certificado_importacion')->where('hcim_id_importacion', 2)->value('hcim_estado_normalizacion'));
        $this->assertSame(0, $this->cuenta('historico_certificado', ['hice_id_importacion' => 2]));
        $this->assertSame(['PRIMERA_VEZ', 'REFRENDO_1'], DB::table('anfeca_modelo.historico_certificado')->where('hice_id_importacion', 1)->orderBy('hice_tipo')->pluck('hice_tipo')->all());

        // Usuario técnico 1:1 (D-1 A): mismo ID y mismo hash.
        $this->assertSame(
            DB::table('public.users')->where('id', 1)->value('password'),
            DB::table('anfeca_modelo.usuario')->where('usua_id_usuario', 1)->value('usua_contrasenia')
        );

        // Conciliación independiente: 0 diferencias.
        $this->artisan('anfeca:pdm-conciliar')->expectsOutputToContain('0 diferencias')->assertExitCode(0);

        // Secuencias alineadas: un nuevo ID no choca con los conservados.
        $this->assertGreaterThan(4, (int) DB::selectOne("select nextval(pg_get_serial_sequence('anfeca_modelo.persona', 'pers_id_persona')) as n")->n);
        $this->assertGreaterThan(3, (int) DB::selectOne("select nextval(pg_get_serial_sequence('anfeca_modelo.solicitud', 'soli_id_solicitud')) as n")->n);

        // Una segunda migración se rechaza (no duplica).
        $this->migrar(['--ejecutar' => true, '--confirmar' => true])->expectsOutputToContain('ya tiene datos')->assertExitCode(1);
        $this->assertSame(4, $this->cuenta('persona'));
    }

    public function test_la_conciliacion_detecta_alteraciones(): void
    {
        config(['anfeca_pdm_decisiones' => $this->decisionesCompletas()]);
        $this->migrar(['--ejecutar' => true, '--confirmar' => true])->assertExitCode(0);

        DB::table('anfeca_modelo.domicilio')->where('domi_id_persona', 1)->update(['domi_calle' => 'Calle Alterada']);
        DB::table('anfeca_modelo.comunicacion')->where('cumu_id_persona', 4)->delete();

        $this->artisan('anfeca:pdm-conciliar')
            ->expectsOutputToContain('Calle Alterada')
            ->expectsOutputToContain('FALTANTE')
            ->assertExitCode(1);
    }

    public function test_vaciar_revierte_y_permite_volver_a_migrar(): void
    {
        config(['anfeca_pdm_decisiones' => $this->decisionesCompletas()]);
        $huella = ProteccionMigracion::huellaPublic();
        $this->migrar(['--ejecutar' => true, '--confirmar' => true])->assertExitCode(0);

        $this->artisan('anfeca:pdm-vaciar', ['--confirmar' => true])->assertExitCode(0);

        foreach (['persona', 'usuario', 'solicitud', 'solicitud_documento', 'historico_certificado_importacion', 'rol', 'zona'] as $tabla) {
            $this->assertSame(0, $this->cuenta($tabla), $tabla);
        }
        $this->assertSame(0, $this->cuenta('estudio_profesional', ['espr_estado' => 'REV']));
        $this->assertSame(32, $this->cuenta('entidad_federativa'), 'los catálogos se conservan');
        $this->assertSame(16, $this->cuenta('estudio_profesional'));
        $this->assertSame($huella, ProteccionMigracion::huellaPublic());

        $this->migrar(['--ejecutar' => true, '--confirmar' => true])->assertExitCode(0);
        $this->artisan('anfeca:pdm-conciliar')->assertExitCode(0);
    }

    public function test_vaciar_se_niega_si_hay_datos_de_etapa_2(): void
    {
        config(['anfeca_pdm_decisiones' => $this->decisionesCompletas()]);
        $this->migrar(['--ejecutar' => true, '--confirmar' => true])->assertExitCode(0);
        DB::table('anfeca_modelo.evaluacion')->insert(['eval_id_solicitud' => 1, 'eval_fecha_evaluacion' => '2026-11-20', 'eval_hora_evaluacion' => '10:00:00']);

        $this->artisan('anfeca:pdm-vaciar', ['--confirmar' => true])->expectsOutputToContain('evaluacion')->assertExitCode(1);
        $this->assertSame(4, $this->cuenta('persona'));
    }

    public function test_valores_que_no_caben_bloquean_sin_truncar(): void
    {
        DB::table('public.datos_solicitantes')->where('id', 1)->update(['numero_exterior' => 'Manzana 12']);
        config(['anfeca_pdm_decisiones' => $this->decisionesCompletas()]);

        $plan = PlanMigracionPdm::construir();
        $codigos = collect($plan->bloqueantes())->map(fn ($i) => "{$i->codigo}#{$i->id}#{$i->decision}")->all();

        $this->assertContains('LONGITUD#1#D-4', $codigos);
        $this->assertSame(0, collect($plan->filas['domicilio'] ?? [])->where('domi_id_persona', 1)->count(), 'no se planea un valor truncado');
    }

    public function test_solicitud_de_usuario_sin_datos_bloquea(): void
    {
        DB::table('public.solicitudes_certificacion')->insert([
            'id' => 9, 'user_id' => 5, 'tipo_tramite' => 'inicial', 'certificacion' => 'ANFECA', 'estado' => 'borrador',
            'compromiso_aceptado' => false, 'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración',
        ]);
        config(['anfeca_pdm_decisiones' => $this->decisionesCompletas()]);

        $codigos = collect(PlanMigracionPdm::construir()->bloqueantes())->map(fn ($i) => "{$i->codigo}#{$i->id}")->all();
        $this->assertSame(['SOLICITUD_SIN_PERSONA#9'], $codigos);
    }


    public function test_otro_historico_sin_descripcion_se_conserva_en_revision(): void
    {
        DB::table('public.formaciones_academicas_solicitud')->where('id', 1)->update([
            'titulo_ultimo_grado' => 'Otro',
            'titulo_ultimo_grado_otro' => null,
        ]);
        $plan = PlanMigracionPdm::construir();

        $incidencia = collect($plan->incidencias)->first(
            fn ($i) => $i->codigo === 'OTRO_SIN_TEXTO' && $i->id === 1
        );
        $this->assertNotNull($incidencia);
        $this->assertFalse($incidencia->bloqueante);

        $formacion = collect($plan->filas['persona_estudio_profesional'])->first(
            fn ($fila) => $fila['pees_id_solicitud'] === 1 && $fila['pees_tipo'] === 'ULTIMO'
        );
        $this->assertSame('Otro', $formacion['espr_nombre']);
        $this->assertSame('REV', $formacion['espr_estado']);

        config(['anfeca_pdm_decisiones' => $this->decisionesCompletas()]);
        $this->migrar(['--simular' => true])
            ->expectsOutputToContain('Simulación correcta')
            ->assertExitCode(0);
    }

    public function test_catalogo_historico_exige_decision_explicita_con_fuente(): void
    {
        DB::table('public.solicitudes_certificacion')->where('id', 1)->update([
            'area_certificacion' => null,
            'tipo_personal' => null,
        ]);
        DB::table('public.selecciones_certificacion')->where('user_id', 1)->update([
            'area_certificacion' => 'Informática',
            'tipo_personal' => 'Tiempo Completo',
        ]);

        $plan = PlanMigracionPdm::construir();
        $this->assertContains('SOLICITUD_INVALIDA',
            collect($plan->bloqueantes())->pluck('codigo')->all());

        $decisiones = $this->decisionesCompletas();
        $decisiones['solicitudes'][1] = [
            'area_certificacion' => 'Informática Administrativa',
            'tipo_personal' => 'Profesoras y profesores de Tiempo Completo',
        ];
        $plan = PlanMigracionPdm::construir($decisiones);
        $this->assertContains('SOLICITUD_INVALIDA',
            collect($plan->bloqueantes())->pluck('codigo')->all(),
            'Sin fuente confirmada no se acepta la equivalencia.');

        $decisiones['solicitudes'][1]['fuente'] = 'Documento de validación ficticio';
        $plan = PlanMigracionPdm::construir($decisiones);
        $this->assertNotContains('SOLICITUD_INVALIDA',
            collect($plan->bloqueantes())->pluck('codigo')->all());
        $this->assertCount(2, collect($plan->incidencias)
            ->where('codigo', 'CATALOGO_POR_DECISION'));
        $this->assertSame(1, collect($plan->filas['solicitud'])
            ->where('soli_id_solicitud', 1)->count());
    }

    public function test_la_base_en_uso_exige_autorizacion_explicita(): void
    {
        config(['anfeca_pdm_decisiones' => $this->decisionesCompletas()]);
        config(['anfeca_modelo.bd_permitida' => DB::connection()->getDatabaseName()]);

        $this->migrar(['--ejecutar' => true, '--confirmar' => true])->expectsOutputToContain('ANFECA_PDM_AUTORIZAR_MIGRACION=si')->assertExitCode(1);
        $this->migrar(['--simular' => true])->expectsOutputToContain('solo se permite en una base aislada')->assertExitCode(1);
        $this->assertSame(0, $this->cuenta('persona'));
    }
}
