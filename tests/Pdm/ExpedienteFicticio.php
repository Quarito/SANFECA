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
 * Construye en anfeca_modelo un expediente FICTICIO completo (solo para pruebas;
 * todo se revierte con la transacción de cada prueba).
 */
trait ExpedienteFicticio
{
    protected function expediente(): array
    {
        $rol = Rol::create(['rol_nombre' => 'solicitante']);
        $zona = Zona::create(['zona_siglas' => 'ZF', 'zona_descripcion' => 'Zona ficticia']);
        $institucion = Institucion::create([
            'inst_numero_afiliacion' => 'F000001', 'inst_nombre' => 'Institución Ficticia',
            'inst_facultad' => 'Facultad Ficticia', 'inst_zona' => 'ZF', 'inst_id_zona' => $zona->zona_id_zona,
        ]);
        Sede::create(['inst_id_institucion' => $institucion->inst_id_institucion, 'sede_descripcion' => 'Campus ficticio']);

        $persona = Persona::create([
            'pers_id_institucion' => $institucion->inst_id_institucion,
            'pers_nombre' => 'Persona', 'pers_apellido_paterno' => 'Ficticia', 'pers_apellido_materno' => 'Uno',
            'pers_curp' => 'FICT800101HDFRRN01', 'pers_rfc' => 'FICT800101AB1', 'pers_genero' => 'F',
            'pers_activo' => true, 'pers_datos_autorizados' => true, 'pers_fecha_autorizacion' => now(),
        ]);
        $usuario = Usuario::create([
            'usua_id_persona' => $persona->pers_id_persona, 'usua_id_rol' => $rol->rol_id_rol,
            'usua_nombre' => 'ficticio1@example.test', 'usua_contrasenia' => Hash::make('ContrasenaFicticia1'),
            'usua_estatus' => 'ACTIVO',
        ]);

        Domicilio::create([
            'domi_id_persona' => $persona->pers_id_persona,
            'domi_id_entidad_federativa' => $this->idCatalogo('entidad_federativa', 'enfe_nombre', 'Ciudad de México'),
            'domi_calle' => 'Calle Ficticia', 'domi_numero_ext' => '10',
            'domi_colonia' => 'Centro', 'domi_municipio' => 'Cuauhtémoc', 'domi_codigo_postal' => '06000',
        ]);
        foreach (['Teléfono celular' => '5500000000', 'Correo electrónico' => 'ficticio1@example.test'] as $tipo => $valor) {
            Comunicacion::create([
                'comu_id_persona' => $persona->pers_id_persona,
                'comu_id_tipo_comunicacion' => $this->idCatalogo('tipo_comunicacion', 'tico_tipo_comunicacion', $tipo),
                'comu_valor' => $valor, 'comu_visible' => true,
            ]);
        }

        $importacion = HistoricoCertificadoImportacion::create([
            'hcim_id_importacion' => 9001, 'hcim_id_usuario' => $usuario->usua_id_usuario,
            'hcim_curp_historica' => 'FICT800101HDFRRN01', 'hcim_certificacion' => 'ANFECA',
            'hcim_primera_vez' => '2019', 'hcim_refrendo_1' => '2023', 'hcim_numero_certificado' => '1000022',
            'hcim_estado_vinculacion' => 'verificado', 'hcim_estado_normalizacion' => 'NORMALIZADO',
        ]);
        foreach (['PRIMERA_VEZ' => '2019', 'REFRENDO_1' => '2023'] as $tipo => $anio) {
            HistoricoCertificado::create([
                'hice_id_importacion' => $importacion->hcim_id_importacion, 'hice_tipo' => $tipo,
                'hice_anio_certificacion' => $anio, 'hice_folio_certificado' => '1000022',
                'hice_curp' => 'FICT800101HDFRRN01', 'hice_certificacion' => 'ANFECA',
            ]);
        }

        $solicitud = Solicitud::create([
            'soli_id_solicitud' => 21, 'soli_folio_solicitud' => 21,
            'soli_id_persona' => $persona->pers_id_persona,
            'soli_id_area_certificacion' => $this->idCatalogo('area_certificacion', 'area_descripcion', 'Administración'),
            'soli_id_tipo_personal_academico' => $this->idCatalogo('tipo_personal_academico', 'tipa_descripcion', 'Profesoras y profesores de Tiempo Completo'),
            'soli_id_articulo' => $this->idCatalogo('articulo_subindice', 'arce_subindice', '14.1'),
            'soli_numero_certificado' => '1000022', 'soli_id_historico_importacion' => $importacion->hcim_id_importacion,
            'soli_fecha_registro_solicitud' => now()->toDateString(),
            'soli_art13' => false, 'soli_art14' => true,
            'soli_tipo_tramite' => 'refrendo', 'soli_certificacion' => 'ANFECA',
        ]);

        foreach (['BO' => 'Borrador', 'FA' => 'Formación académica autorizada', 'CA' => 'Compromiso de entrega aceptado', 'EN' => 'Enviada'] as $siglas => $estado) {
            EstadoSolicitud::create([
                'esso_id_c_estado_solicitud' => $this->idCatalogo('c_estado_solicitud', 'ceso_estado_solicitud', $estado),
                'esso_id_solicitud' => $solicitud->soli_id_solicitud, 'esso_descripcion' => $estado,
                'esso_siglas' => $siglas, 'esso_fecha' => now()->toDateString(), 'esso_hora' => now()->format('H:i:s'),
            ]);
        }

        $licenciatura = $this->idCatalogo('nivel_profesional', 'nipr_nivel_profesional', 'Licenciatura');
        $otro = EstudioProfesional::create([
            'espr_id_nivel_profesional' => $this->idCatalogo('nivel_profesional', 'nipr_nivel_profesional', 'Maestría'),
            'espr_nombre' => 'Finanzas corporativas', 'espr_estado' => 'REV',
        ]);
        $origen = DB::table('anfeca_modelo.estudio_profesional')
            ->where('espr_id_nivel_profesional', $licenciatura)->where('espr_nombre', 'Administración')->value('espr_id_estudio_profesional');

        foreach (['ORIGEN' => $origen, 'ULTIMO' => $otro->espr_id_estudio_profesional] as $tipo => $estudio) {
            PersonaEstudioProfesional::create([
                'pees_id_estuido_profesional' => $estudio, 'pees_id_persona' => $persona->pers_id_persona,
                'pees_id_solicitud' => $solicitud->soli_id_solicitud, 'pees_tipo' => $tipo,
                'pees_url_titulo' => "anfeca/documentos/solicitudes/21/titulo_{$tipo}.pdf",
                'pees_url_cedula' => "anfeca/documentos/solicitudes/21/cedula_{$tipo}.pdf",
            ]);
        }

        $tituloUltimo = $this->idCatalogo('documento', 'docu_descripcion', 'Título del último grado');
        $versiones = [];
        foreach (['anfeca/documentos/solicitudes/21/titulo_v1.pdf', 'anfeca/documentos/solicitudes/21/titulo_v2.pdf'] as $i => $ruta) {
            $versiones[] = $doc = SolicitudDocumento::create([
                'socu_id_solicitud' => $solicitud->soli_id_solicitud, 'socu_id_documento' => $tituloUltimo,
                'socu_fecha' => now()->toDateString(), 'socu_hora' => now()->format('H:i:s'), 'socu_url_archivo' => $ruta,
            ]);
            EstadoSolicitudDocumento::create([
                'esdo_id_documentacion' => $doc->socu_id_documentacion,
                'esdo_id_estado_documento' => $this->idCatalogo('estado_documento', 'esdo_estado_documento', $i === 0 ? 'Sustituido' : 'Cargado'),
                'esdo_observacion' => $i === 0 ? 'Reemplazado por una versión nueva' : 'Archivo vigente',
                'esdo_fecha' => now()->toDateString(), 'esdo_hora' => now()->format('H:i:s'),
            ]);
        }

        Pago::create(['pago_id_solicitud' => $solicitud->soli_id_solicitud, 'pago_url_comprobante' => 'anfeca/documentos/solicitudes/21/pago.pdf']);

        return compact('persona', 'usuario', 'solicitud', 'importacion', 'versiones');
    }

    /** Cadena de etapa 2: evaluación → certificado → refrendo, dictamen y acta. */
    protected function etapa2(Solicitud $s): Evaluacion
    {
        $evaluacion = Evaluacion::create([
            'eval_id_solicitud' => $s->soli_id_solicitud, 'eval_puntuacion' => 80,
            'eval_fecha_evaluacion' => now()->toDateString(), 'eval_hora_evaluacion' => now()->format('H:i:s'),
        ]);
        $certificado = Certificado::create([
            'cert_id_evaluacion' => $evaluacion->eval_id_evaluacion, 'cert_folio' => 'FICT-0001', 'cert_anio' => '2026',
        ]);
        RefrendoCertificado::create([
            'rece_id_evaluacion' => $evaluacion->eval_id_evaluacion, 'rece_id_certificado' => $certificado->cert_id_certificado,
            'rece_numero_refrendo' => 2, 'rece_anio_refrendo' => 2026, 'rece_art13' => false, 'rece_art14' => true,
        ]);
        $tipo = TipoDictamen::create(['tidi_descripcion' => 'Tipo ficticio', 'tidi_tipo_dictamen_estado' => true]);
        Dictamen::create([
            'dict_id_tipo_dictamen' => $tipo->tidi_id_tipo_dictamen, 'dict_id_evaluacion' => $evaluacion->eval_id_evaluacion,
            'dict_encabezado' => 'FICT', 'dict_folio_dictamen' => 'F-001',
            'dict_fecha_dictamen_creacion' => now()->toDateString(), 'dict_hora_dictamen_creacion' => now()->format('H:i:s'),
        ]);
        Acta::create(['acta_id_solicitud' => $s->soli_id_solicitud, 'acta_sede' => 'Sede ficticia']);

        return $evaluacion;
    }
}
