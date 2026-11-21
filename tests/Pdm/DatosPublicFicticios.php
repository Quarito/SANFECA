<?php

namespace Tests\Pdm;

use Illuminate\Support\Facades\DB;

/**
 * Escenario FICTICIO en public que reproduce los casos reales observados en el
 * servidor (consultas A–F, 20/11/2026) sin usar ningún dato real.
 *
 *  users 1–4 con datos; 5 y 6 sin datos personales.
 *  Persona 1: «cdmx» · 2: «Monterrey» (D-13) · 3: «Entidad de Prueba» (D-13) · 4: «quintana roo», género vacío.
 *  Institución 1 sin número de afiliación válido (D-11).
 *  Histórico 1 válido (con refrendo) · 2 número no válido (D-14) · 3 válido sin usuario.
 *  Solicitud 1: inicial enviada completa (Otro, historial, enlace, pago).
 *  Solicitud 2: refrendo art. 14.1 en borrador con área vacía (N-6).
 *  Solicitud 3: inicial en borrador sin formación.
 */
trait DatosPublicFicticios
{
    /** Decisiones que resuelven todos los bloqueos del escenario. */
    protected function decisionesCompletas(): array
    {
        return [
            'base_de_datos' => DB::connection()->getDatabaseName(),
            'instituciones' => [1 => ['numero_afiliacion' => 'F000001', 'facultad' => 'Facultad Ficticia', 'fuente' => 'Prueba']],
            'domicilios' => [2 => ['entidad' => 'Nuevo León', 'fuente' => 'Prueba'], 3 => 'sin_domicilio'],
            'certificados' => [2 => 'conservar_en_revision'],
            'regla_solo_art13' => 'refrendo_sin_articulo_14',
        ];
    }

    protected function crearPublicFicticio(): void
    {
        $t = '2026-11-10 18:00:00'; // UTC → 12:00:00 en America/Mexico_City

        DB::table('public.roles')->insert(['id' => 1, 'nombre' => 'administrador', 'descripcion' => 'Rol ficticio']);
        DB::table('public.privilegios')->insert(['id' => 1, 'nombre' => 'revisar', 'descripcion' => 'Privilegio ficticio']);
        DB::table('public.rol_privilegio')->insert(['id' => 1, 'rol_id' => 1, 'privilegio_id' => 1]);
        DB::table('public.zonas')->insert(['id' => 1, 'siglas' => 'ZC', 'descripcion' => 'Zona ficticia']);
        DB::table('public.instituciones_afiliadas')->insert([
            'id' => 1, 'numero_afiliacion' => null, 'nombre' => 'Institución Ficticia', 'sede' => 'Sede ficticia',
            'zona_anfeca' => null, 'zona_id' => 1, 'campus' => 'Campus ficticio',
        ]);

        foreach (range(1, 6) as $n) {
            DB::table('public.users')->insert([
                'id' => $n, 'name' => "Persona Ficticia {$n}", 'email' => "ficticio{$n}@example.test",
                'password' => '$2y$04$'.str_repeat('a', 53), 'rol' => 'solicitante', 'created_at' => $t, 'updated_at' => $t,
            ]);
        }

        $persona = fn (int $n, string $entidad, string $genero, string $numExt = '10') => [
            'id' => $n, 'user_id' => $n, 'curp' => sprintf('FICT80010%dHDFRRN0%d', $n, $n), 'rfc' => sprintf('FICT80010%dAB%d', $n, $n),
            'nombres' => 'Persona', 'apellido_paterno' => 'Ficticia', 'apellido_materno' => (string) $n, 'genero' => $genero,
            'calle' => 'Calle Ficticia', 'numero_exterior' => $numExt, 'numero_interior' => null, 'codigo_postal' => '01000',
            'entidad_federativa' => $entidad, 'colonia' => 'Colonia Ficticia', 'municipio' => 'Municipio Ficticio',
            'correo_electronico' => "ficticio{$n}@example.test", 'telefono_particular' => null, 'telefono_celular' => '55000000'.$n.$n,
            'datos_autorizados' => true, 'fecha_autorizacion' => $t, 'created_at' => $t, 'updated_at' => $t,
        ];
        DB::table('public.datos_solicitantes')->insert([
            $persona(1, 'cdmx', 'Femenino'),
            $persona(2, 'Monterrey', 'Masculino'),
            $persona(3, 'Entidad de Prueba', 'Masculino'),
            $persona(4, 'quintana roo', ''),
        ]);

        DB::table('public.coordinador_zona')->insert(['id' => 1, 'user_id' => 1, 'zona_id' => 1]);

        $hist = fn (int $id, ?int $user, ?string $numero, ?string $primera, ?string $ref1) => [
            'id' => $id, 'user_id' => $user, 'numero_certificado' => $numero, 'primera_vez' => $primera, 'refrendo_1' => $ref1,
            'curp_historica' => 'FICT800104HDFRRN04', 'certificacion' => 'ANFECA', 'lote_importacion' => 'L-PRUEBA',
            'fila_origen' => $id, 'estado_vinculacion' => $user ? 'verificado' : 'pendiente', 'created_at' => $t, 'updated_at' => $t,
        ];
        DB::table('public.certificados_historicos')->insert([
            $hist(1, 4, '1000022', '2019', '2023'),
            $hist(2, null, 'ANF-77', '2018', null),
            $hist(3, null, '55', '2020', null),
        ]);

        DB::table('public.selecciones_certificacion')->insert([
            ['id' => 1, 'user_id' => 1, 'certificacion' => 'ANFECA', 'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración'],
            ['id' => 2, 'user_id' => 4, 'certificacion' => 'ANFECA', 'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Informática Administrativa'],
        ]);

        DB::table('public.solicitudes_certificacion')->insert([
            ['id' => 1, 'user_id' => 1, 'tipo_tramite' => 'inicial', 'certificacion' => 'ANFECA', 'estado' => 'enviado',
                'fecha_envio' => '2026-11-12 20:30:00', 'certificado_historico_id' => null,
                'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración',
                'compromiso_aceptado' => true, 'compromiso_aceptado_en' => '2026-11-12 20:00:00', 'created_at' => $t, 'updated_at' => $t],
            ['id' => 2, 'user_id' => 4, 'tipo_tramite' => 'refrendo', 'certificacion' => 'ANFECA', 'estado' => 'borrador',
                'fecha_envio' => null, 'certificado_historico_id' => 1, 'tipo_personal' => null, 'area_certificacion' => '',
                'compromiso_aceptado' => false, 'compromiso_aceptado_en' => null, 'created_at' => $t, 'updated_at' => $t],
            ['id' => 3, 'user_id' => 2, 'tipo_tramite' => 'inicial', 'certificacion' => 'ANFECA', 'estado' => 'borrador',
                'fecha_envio' => null, 'certificado_historico_id' => null,
                'tipo_personal' => 'Profesoras y profesores de Tiempo Completo', 'area_certificacion' => 'Administración',
                'compromiso_aceptado' => false, 'compromiso_aceptado_en' => null, 'created_at' => $t, 'updated_at' => $t],
        ]);

        DB::table('public.datos_refrendo')->insert([
            'id' => 1, 'solicitud_id' => 2, 'numero_refrendo_asignado' => null, 'refrendo_actual' => 1,
            'aplica_articulo_14' => true, 'inciso_articulo_14' => '14.1', 'created_at' => $t, 'updated_at' => $t,
        ]);

        $ruta = fn (int $s, string $c) => "anfeca/documentos/solicitudes/{$s}/{$c}.pdf";
        DB::table('public.formaciones_academicas_solicitud')->insert([
            'id' => 1, 'solicitud_id' => 1, 'nivel_origen' => 'Licenciatura', 'titulo_origen' => 'Administración',
            'nivel_ultimo_grado' => 'Maestría', 'titulo_ultimo_grado' => 'Otro', 'titulo_ultimo_grado_otro' => 'Finanzas corporativas',
            'institucion_afiliada_id' => 1,
            'titulo_origen_archivo' => $ruta(1, 'titulo_origen'), 'cedula_origen_archivo' => $ruta(1, 'cedula_origen'),
            'titulo_ultimo_grado_archivo' => $ruta(1, 'titulo_ultimo_v2'), 'cedula_ultimo_grado_archivo' => $ruta(1, 'cedula_ultimo'),
            'formacion_completada_at' => '2026-11-11 17:15:00', 'created_at' => $t, 'updated_at' => $t,
        ]);
        DB::table('public.historial_documentos_academicos')->insert([
            'id' => 1, 'solicitud_id' => 1, 'tipo_documento' => 'titulo_ultimo_grado_archivo',
            'ruta_archivo' => $ruta(1, 'titulo_ultimo_v1'), 'created_at' => '2026-11-11 16:00:00', 'updated_at' => '2026-11-11 16:00:00',
        ]);
        DB::table('public.documentos_probatorios_solicitud')->insert([
            'id' => 1, 'solicitud_id' => 1, 'constancia_antiguedad_archivo' => $ruta(1, 'constancia'),
            'comprobante_pago_archivo' => $ruta(1, 'pago'), 'link' => 'https://example.test/evidencias',
            'created_at' => $t, 'updated_at' => $t,
        ]);
    }
}
