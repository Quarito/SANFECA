<?php

namespace Tests\Pdm36;

use App\Auth\UsuarioPdm36;
use App\Services\Pdm36\EsquemaPdm36;
use App\Services\Solicitantes\EstadoSolicitante;
use Database\Seeders\Pdm36\CatalogosPdm36Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Autenticación, roles y navegación inicial EXCLUSIVAMENTE sobre las 36 tablas aprobadas.
 * La base se reconstruye en cada prueba (solo bd_anfeca_pdm36_pruebas) y se registra todo el SQL ejecutado para
 * demostrar que no se usa ninguna tabla de public ni de anfeca_compat.
 */
class AutenticacionPdm36Test extends TestCase
{
    private const BASE = 'bd_anfeca_pdm36_pruebas';

    private const CLAVE = 'ContrasenaFicticia1';

    /** @var list<string> */
    private array $sql = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== self::BASE || DB::selectOne('select current_database() as b')->b !== self::BASE) {
            throw new RuntimeException('Esta suite solo se ejecuta sobre '.self::BASE.'.');
        }
        DB::unprepared('drop schema if exists anfeca_modelo cascade');
        foreach (DB::select("select tablename from pg_tables where schemaname = 'public'") as $t) {
            throw new RuntimeException("La base de prueba contiene public.{$t->tablename}: debe estar vacía.");
        }
        EsquemaPdm36::instalar();
        (new CatalogosPdm36Seeder())->run();
        DB::listen(fn ($q) => $this->sql[] = $q->sql);
    }

    private function usuario(string $correo, string $rol, string $estatus = UsuarioPdm36::ESTATUS_REGISTRADO, string $curp = 'FICT800101HDFRRN01'): int
    {
        $e = 'anfeca_modelo.';
        $persona = DB::table($e.'persona')->insertGetId(['pers_nombre' => 'Persona', 'pers_apellido_paterno' => 'Ficticia',
            'pers_curp' => $curp, 'pers_rfc' => substr($curp, 0, 10).'AB1', 'pers_genero' => 'F', 'pers_activo' => true], 'pers_id_persona');
        $tipo = DB::table($e.'tipo_comunicacion')->where('tico_tipo_comunicacion', 'Correo electrónico')->value('tico_id_tipo_comunicacion');
        DB::table($e.'comunicacion')->insert(['comu_id_comunicacion' => 1, 'comu_id_persona' => $persona, 'comu_id_tipo_comunicacion' => $tipo, 'comu_valor' => $correo, 'comu_visible' => true]);

        return (int) DB::table($e.'usuario')->insertGetId(['usua_id_persona' => $persona, 'usua_nombre' => $correo, 'usua_contrasenia' => Hash::make(self::CLAVE),
            'usua_estatus' => $estatus, 'usua_id_rol' => DB::table($e.'rol')->where('rol_nombre', $rol)->value('rol_id_rol')], 'usua_id_usuario');
    }

    private function solicitudConEvento(int $usuario, string $siglas): void
    {
        $e = 'anfeca_modelo.';
        $persona = DB::table($e.'usuario')->where('usua_id_usuario', $usuario)->value('usua_id_persona');
        $sol = DB::table($e.'solicitud')->insertGetId(['soli_id_persona' => $persona, 'soli_id_area_certificacion' => DB::table($e.'area_certificacion')->min('area_id_area_certificacion'),
            'soli_id_tipo_personal_academico' => DB::table($e.'tipo_personal_academico')->min('tipa_id_tipo_personal_academico'),
            'soli_fecha_registro_solicitud' => now()->toDateString(), 'soli_folio_solicitud' => 1, 'solo_art13' => false, 'soli_art14' => false], 'soli_id_solicitud');
        DB::table($e.'estado_solicitud')->insert(['esso_id_solicitud' => $sol, 'esso_siglas' => $siglas, 'esso_descripcion' => config('anfeca_pdm.catalogos.estados_solicitud')[$siglas],
            'esso_fecha' => now()->toDateString(), 'esso_hora' => now()->format('H:i:s')]);
    }

    private function sinDependenciasAjenas(): void
    {
        $ajenas = preg_grep('/\b(public\.|anfeca_compat|users|datos_solicitantes|solicitudes_certificacion|selecciones_certificacion|sessions|password_reset_tokens|cache|jobs|integracion_control|zona\b)/i', $this->sql);
        $this->assertSame([], array_values($ajenas), 'Consultas fuera del modelo aprobado.');
        $this->assertSame(EsquemaPdm36::ESPERADO, EsquemaPdm36::inventario());
        $this->assertSame(0, EsquemaPdm36::tablasAjenas());
    }

    public function test_la_base_contiene_exactamente_el_modelo_aprobado(): void
    {
        $this->assertSame(['tablas' => 36, 'columnas' => 173, 'pk' => 36, 'fk' => 35, 'indices' => 96, 'secuencias' => 36], EsquemaPdm36::inventario());
        $this->assertSame(0, EsquemaPdm36::tablasAjenas());
        $cols = collect(DB::select("select table_name||'.'||column_name as c from information_schema.columns where table_schema = 'anfeca_modelo'"))->pluck('c');
        $this->assertTrue($cols->contains('comunicacion.comu_id_persona') && $cols->contains('solicitud_documento.socu_id_documento'), 'Correcciones autorizadas.');
        $this->assertTrue($cols->contains('convocatoria.con_lugar_entrega') && $cols->contains('solicitud.solo_art13'), 'Renombres pendientes NO aplicados.');
        $this->assertSame(9, DB::table('anfeca_modelo.rol')->count());
    }

    public function test_solicitante_accede_y_navega_segun_sus_solicitudes(): void
    {
        $id = $this->usuario('solicitante.pdm36@example.test', 'solicitante');
        $this->post('/login', ['email' => 'SOLICITANTE.pdm36@example.test', 'password' => self::CLAVE])->assertRedirect(route('mis-datos.edit'));
        $this->assertAuthenticatedAs(UsuarioPdm36::find($id));
        $this->assertSame('solicitante', auth()->user()->rol);
        $this->assertSame('Persona Ficticia', auth()->user()->name);
        $this->assertSame('solicitante.pdm36@example.test', auth()->user()->correoComunicacion());
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->solicitudConEvento($id, 'EN');
        $this->post('/login', ['email' => 'solicitante.pdm36@example.test', 'password' => self::CLAVE])->assertRedirect(route('registro.completado'));
        $this->post('/logout');
        $this->solicitudConEvento($id, 'BO');
        $this->post('/login', ['email' => 'solicitante.pdm36@example.test', 'password' => self::CLAVE])->assertRedirect(route('mis-datos.edit'));
        $this->get('/')->assertRedirect(route('mis-datos.edit'));
        $this->sinDependenciasAjenas();
    }

    public function test_credenciales_incorrectas_y_cuenta_de_baja_se_rechazan(): void
    {
        $this->usuario('baja.pdm36@example.test', 'solicitante', 'BAJA');
        $this->post('/login', ['email' => 'baja.pdm36@example.test', 'password' => self::CLAVE])->assertSessionHasErrors('email');
        $this->usuario('otro.pdm36@example.test', 'solicitante', curp: 'FICT800102HDFRRN02');
        $this->post('/login', ['email' => 'otro.pdm36@example.test', 'password' => 'Incorrecta123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->sinDependenciasAjenas();
    }

    public function test_coordinacion_nacional_y_de_zona_por_rol_del_modelo(): void
    {
        $n = $this->usuario('nacional.pdm36@example.test', 'coordinador_nacional');
        $z = $this->usuario('zona3.pdm36@example.test', 'coordinador_zona_3', curp: 'FICT800103HDFRRN03');
        $this->post('/login', ['email' => 'nacional.pdm36@example.test', 'password' => self::CLAVE])->assertRedirect(route('coordinacion.inicio'));
        $this->assertTrue(auth()->user()->esCoordinadorNacional());
        $this->get('/registro/certificacion')->assertRedirect(route('coordinacion.inicio'));   // regla vigente de SoloSolicitantes
        $this->post('/logout');
        $this->post('/login', ['email' => 'zona3.pdm36@example.test', 'password' => self::CLAVE])->assertRedirect(route('coordinacion.inicio'));
        $this->assertTrue(auth()->user()->esCoordinadorZona());
        $this->assertSame(3, auth()->user()->zona());
        $this->assertNull(UsuarioPdm36::find($n)->zona());
        $this->sinDependenciasAjenas();
    }

    public function test_solicitante_sin_acceso_a_coordinacion_y_autorizacion_de_datos_en_usuario(): void
    {
        $id = $this->usuario('acceso.pdm36@example.test', 'solicitante');
        $this->post('/login', ['email' => 'acceso.pdm36@example.test', 'password' => self::CLAVE]);
        $this->get('/coordinacion')->assertStatus(403);
        $estado = app(EstadoSolicitante::class);
        $this->assertFalse($estado->datosAutorizados($id));
        $this->get('/registro/certificacion')->assertRedirect(route('mis-datos.edit'));
        DB::table('anfeca_modelo.usuario')->where('usua_id_usuario', $id)->update(['usua_estatus' => UsuarioPdm36::ESTATUS_AUTORIZADO]);
        $this->assertTrue($estado->datosAutorizados($id));
        $this->sinDependenciasAjenas();
    }
}
