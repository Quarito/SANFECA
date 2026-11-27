<?php

namespace Tests\Pdm;

use App\Models\Pdm\AreaCertificacion;
use App\Models\Pdm\Documento;
use App\Models\Pdm\EstadoDocumento;
use App\Models\Pdm\EstudioProfesional;
use App\Models\Pdm\NivelProfesional;
use App\Models\Pdm\Persona;
use App\Models\Pdm\Rol;
use App\Models\Pdm\TipoComunicacion;
use App\Models\Pdm\TipoPersonalAcademico;
use App\Models\Pdm\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AutenticacionModeloOficialTest extends PdmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('bd_anfeca_pruebas_pg', DB::connection()->getDatabaseName());
        // DDL transaccional sobre la base de PHPUnit. El teardown recupera los esquemas de la suite.
        DB::unprepared('drop schema anfeca_compat cascade; drop schema anfeca_modelo cascade; drop schema public cascade; create schema public;');
        DB::unprepared(file_get_contents(database_path('sql/anfeca_modelo/anfeca_modelo.sql')));

        $tablas = DB::select("select tablename from pg_tables where schemaname not in ('pg_catalog', 'information_schema')");
        $this->assertCount(36, $tablas);
    }

    public function test_autentica_y_relee_persona_rol_y_correo_solo_con_las_tablas_oficiales(): void
    {
        $usuario = $this->crearUsuario('ana@example.test');
        $tipo = TipoComunicacion::create(['tico_tipo_comunicacion' => 'CORREO', 'tico_estado' => 'ACT']);
        $comunicacion = $usuario->persona->comunicaciones()->create([
            'comu_id_tipo_comunicacion' => $tipo->getKey(),
            'comu_valor' => 'contacto@example.test',
        ]);

        $guard = Auth::guard('pdm');
        $this->assertTrue($guard->attempt(['email' => '  ANA@example.test ', 'password' => 'clave-ficticia']));
        $guard->forgetUser();
        $cuenta = $guard->user();
        $this->assertSame($usuario->getKey(), $cuenta->getAuthIdentifier());
        $this->assertSame('Ana', $cuenta->persona->pers_nombre);
        $this->assertSame('solicitante', $cuenta->rol->rol_nombre);
        $this->assertSame('contacto@example.test', $comunicacion->fresh()->comu_valor);
        $this->assertArrayNotHasKey('usua_contrasenia', $cuenta->toArray());
        $guard->logout();
        $this->assertFalse($guard->check());
    }

    public function test_rechaza_clave_incorrecta_cuenta_inactiva_y_nombre_ambiguo(): void
    {
        $usuario = $this->crearUsuario('ana@example.test');
        $guard = Auth::guard('pdm');
        $this->assertFalse($guard->attempt(['email' => 'ana@example.test', 'password' => 'incorrecta']));
        $this->assertTrue($guard->attempt(['email' => 'ana@example.test', 'password' => 'clave-ficticia']));
        $usuario->update(['usua_estatus' => 'INACTIVO']);
        $guard->forgetUser();
        $this->assertFalse($guard->check());
        $this->assertFalse($guard->attempt(['email' => 'ana@example.test', 'password' => 'clave-ficticia']));
        $this->crearUsuario('ANA@example.test');
        $this->assertFalse($guard->attempt(['email' => 'ana@example.test', 'password' => 'clave-ficticia']));
    }

    public function test_lee_certificado_refrendo_estudios_y_seguimiento_por_relaciones_oficiales(): void
    {
        $persona = $this->crearUsuario('ana@example.test')->persona;
        $area = AreaCertificacion::create(['area_descripcion' => 'Contaduría']);
        $tipo = TipoPersonalAcademico::create(['tipa_descripcion' => 'Docente', 'tipa_estado' => true]);
        $solicitud = $persona->solicitudes()->create([
            'soli_id_area_certificacion' => $area->getKey(),
            'soli_id_tipo_personal_academico' => $tipo->getKey(),
            'soli_fecha_registro_solicitud' => '2026-11-27', 'soli_folio_solicitud' => 1,
            'soli_art13' => false, 'soli_art14' => false,
        ]);
        $evaluacion = $solicitud->evaluaciones()->create([
            'eval_fecha_evaluacion' => '2026-11-27', 'eval_hora_evaluacion' => '10:00:00',
        ]);
        $certificado = $evaluacion->certificados()->create(['cert_folio' => 'FICTICIO', 'cert_anio' => '2026']);
        $refrendo = $certificado->refrendoCertificados()->create([
            'rece_numero_refrendo' => 1, 'rece_anio_refrendo' => 2029, 'rece_art13' => false,
        ]);

        $nivel = NivelProfesional::create(['nipr_nivel_profesional' => 'Licenciatura']);
        $estudio = EstudioProfesional::create([
            'espr_id_nivel_profesional' => $nivel->getKey(), 'espr_nombre' => 'Contaduría', 'espr_estado' => 'ACT',
        ]);
        $formacion = $persona->personaEstudioProfesionales()->create([
            'pees_id_estuido_profesional' => $estudio->getKey(),
            'pees_cedula' => '1234567', 'pees_url_titulo' => 'ficticio/titulo.pdf',
        ]);
        $documento = Documento::create(['docu_descripcion' => 'Constancia', 'docu_estatus' => 'ACT']);
        $cargada = $solicitud->solicitudDocumentos()->create([
            'socu_id_documento' => $documento->getKey(), 'socu_fecha' => '2026-11-27', 'socu_hora' => '10:00:00',
        ]);
        $estado = EstadoDocumento::create(['esdo_estado_documento' => 'Recibido', 'esdo_estado' => 'ACT']);
        $cargada->estadoSolicitudDocumentos()->create([
            'esdo_id_estado_documento' => $estado->getKey(), 'esdo_observacion' => 'Documento recibido',
            'esdo_fecha' => '2026-11-27', 'esdo_hora' => '10:00:00',
        ]);

        $this->assertSame($persona->getKey(), $refrendo->certificado->evaluacion->solicitud->persona->getKey());
        $this->assertSame('Licenciatura', $formacion->fresh()->estudioProfesional->nivelProfesional->nipr_nivel_profesional);
        $this->assertSame('1234567', $formacion->fresh()->pees_cedula);
        $this->assertSame('Recibido', $cargada->estadoSolicitudDocumentos()->sole()->estadoDocumento->esdo_estado_documento);
    }

    private function crearUsuario(string $correo): Usuario
    {
        $persona = Persona::create(['pers_nombre' => 'Ana', 'pers_curp' => 'AAAA010101MDFBBB09', 'pers_rfc' => 'AAAA0101016A2']);
        $rol = Rol::firstOrCreate(['rol_nombre' => 'solicitante']);

        return Usuario::create([
            'usua_id_persona' => $persona->getKey(),
            'usua_id_rol' => $rol->getKey(),
            'usua_nombre' => $correo,
            'usua_contrasenia' => Hash::make('clave-ficticia'),
            'usua_estatus' => Usuario::ESTATUS_ACTIVO,
        ]);
    }
}
