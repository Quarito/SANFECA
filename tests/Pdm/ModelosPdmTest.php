<?php

namespace Tests\Pdm;

use App\Models\Pdm\Acta;
use App\Models\Pdm\Certificado;
use App\Models\Pdm\Comunicacion;
use App\Models\Pdm\Dictamen;
use App\Models\Pdm\Domicilio;
use App\Models\Pdm\EstadoSolicitud;
use App\Models\Pdm\EstadoSolicitudDocumento;
use App\Models\Pdm\EstudioProfesional;
use App\Models\Pdm\Evaluacion;
use App\Models\Pdm\HistoricoCertificado;
use App\Models\Pdm\HistoricoCertificadoImportacion;
use App\Models\Pdm\Institucion;
use App\Models\Pdm\Pago;
use App\Models\Pdm\Persona;
use App\Models\Pdm\PersonaEstudioProfesional;
use App\Models\Pdm\RefrendoCertificado;
use App\Models\Pdm\Rol;
use App\Models\Pdm\Sede;
use App\Models\Pdm\Solicitud;
use App\Models\Pdm\SolicitudDocumento;
use App\Models\Pdm\TipoDictamen;
use App\Models\Pdm\Usuario;
use App\Models\Pdm\Zona;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Escritura y lectura REAL en anfeca_modelo con los modelos Eloquent y sus
 * relaciones, recorriendo el expediente completo de un trámite FICTICIO:
 * persona → usuario → domicilio/contacto → solicitud de refrendo art. 14 →
 * eventos → formación (incluido «Otro») → documentos con historial → pago,
 * y la cadena de etapa 2 (evaluación → certificado → refrendo, dictamen, acta).
 * Todo se revierte al terminar la prueba.
 */
class ModelosPdmTest extends PdmTestCase
{
    use ExpedienteFicticio;

    public function test_expediente_completo_se_escribe_y_se_lee_por_relaciones(): void
    {
        ['persona' => $p, 'usuario' => $u, 'solicitud' => $s] = $this->expediente();

        $persona = Persona::with(['usuarios.rol', 'domicilios.entidadFederativa', 'comunicaciones', 'institucion.zona', 'solicitudes'])
            ->findOrFail($p->pers_id_persona);

        $this->assertSame('ficticio1@example.test', $persona->usuarios->first()->usua_nombre);
        $this->assertSame('solicitante', $persona->usuarios->first()->rol->rol_nombre);
        $this->assertSame('Ciudad de México', $persona->domicilios->first()->entidadFederativa->enfe_nombre);
        $this->assertSame('Cuauhtémoc', $persona->domicilios->first()->domi_municipio);
        $this->assertCount(2, $persona->comunicaciones);
        $this->assertSame('ZF', $persona->institucion->zona->zona_siglas);
        $this->assertTrue($persona->pers_datos_autorizados);

        $solicitud = Solicitud::with(['areaCertificacion', 'tipoPersonalAcademico', 'articuloSubindice', 'historicoCertificadoImportacion.historicoCertificados', 'estadoSolicitudes', 'personaEstudioProfesionales.estudioProfesional.nivelProfesional', 'solicitudDocumentos.estadoSolicitudDocumentos.estadoDocumento', 'pagos'])
            ->findOrFail(21);

        $this->assertSame(21, $solicitud->soli_folio_solicitud);
        $this->assertSame('refrendo', $solicitud->soli_tipo_tramite);
        $this->assertSame('Administración', $solicitud->areaCertificacion->area_descripcion);
        $this->assertSame('14.1', $solicitud->articuloSubindice->arce_subindice);
        $this->assertTrue($solicitud->soli_art14);
        $this->assertCount(2, $solicitud->historicoCertificadoImportacion->historicoCertificados);
        $this->assertSame(['BO', 'FA', 'CA', 'EN'], $solicitud->estadoSolicitudes->sortBy('esso_id__estado_solicitud')->pluck('esso_siglas')->values()->all());

        $ultimo = $solicitud->personaEstudioProfesionales->firstWhere('pees_tipo', 'ULTIMO');
        $this->assertSame('Finanzas corporativas', $ultimo->estudioProfesional->espr_nombre);
        $this->assertSame('REV', $ultimo->estudioProfesional->espr_estado);
        $this->assertSame('Maestría', $ultimo->estudioProfesional->nivelProfesional->nipr_nivel_profesional);

        // Historial: dos versiones del mismo documento; la sustituida se conserva.
        $this->assertCount(2, $solicitud->solicitudDocumentos);
        $estados = $solicitud->solicitudDocumentos->sortBy('socu_id_documentacion')
            ->map(fn ($d) => $d->estadoSolicitudDocumentos->first()->estadoDocumento->esdo_estado_documento)->values()->all();
        $this->assertSame(['Sustituido', 'Cargado'], $estados);

        $this->assertSame('anfeca/documentos/solicitudes/21/pago.pdf', $solicitud->pagos->first()->pago_url_comprobante);
        $this->assertSame($u->usua_id_usuario, $solicitud->historicoCertificadoImportacion->usuario->usua_id_usuario);
    }

    public function test_actualizacion_y_lectura_posterior(): void
    {
        ['persona' => $p] = $this->expediente();

        Domicilio::where('domi_id_persona', $p->pers_id_persona)->firstOrFail()->update(['domi_calle' => 'Calle Nueva']);

        $this->assertSame('Calle Nueva', DB::table('anfeca_modelo.domicilio')->where('domi_id_persona', $p->pers_id_persona)->value('domi_calle'));
    }

    public function test_contactos_con_el_mismo_serial_usan_la_llave_primaria_completa(): void
    {
        ['persona' => $persona] = $this->expediente();
        [$primero, $segundo] = $persona->comunicaciones()->orderBy('comu_id_comunicacion')->get()->all();
        $valorPrimero = $primero->comu_valor;

        $segundo->update(['comu_id_comunicacion' => $primero->comu_id_comunicacion]);
        $segundo->update(['comu_valor' => 'Contacto actualizado']);

        $this->assertSame('Contacto actualizado', $segundo->fresh()->comu_valor);
        $this->assertSame($valorPrimero, $primero->fresh()->comu_valor);

        $segundo->delete();
        $this->assertSame($valorPrimero, $primero->fresh()->comu_valor);
        $this->assertNull($segundo->fresh());
    }

    public function test_cadena_de_etapa_2_lista_para_el_modulo_administrativo(): void
    {
        ['solicitud' => $s] = $this->expediente();

        $evaluacion = $this->etapa2($s);

        $evaluacion = Evaluacion::with(['certificados.refrendoCertificados', 'dictamenes.tipoDictamen', 'solicitud.actas'])->findOrFail($evaluacion->eval_id_evaluacion);

        $this->assertSame(2, $evaluacion->certificados->first()->refrendoCertificados->first()->rece_numero_refrendo);
        $this->assertSame('Tipo ficticio', $evaluacion->dictamenes->first()->tipoDictamen->tidi_descripcion);
        $this->assertCount(1, $evaluacion->solicitud->actas);
    }

    public function test_todo_se_revierte_al_terminar_cada_prueba(): void
    {
        // Las pruebas anteriores crearon datos; ninguno debe persistir.
        foreach (['persona', 'usuario', 'solicitud', 'solicitud_documento', 'historico_certificado_importacion', 'evaluacion'] as $tabla) {
            $this->assertSame(0, DB::table("anfeca_modelo.{$tabla}")->count(), $tabla);
        }
    }
}
