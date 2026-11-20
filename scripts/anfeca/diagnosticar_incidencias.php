<?php

/*
|--------------------------------------------------------------------------
| ANFECA · Diagnóstico de SOLO LECTURA de las incidencias bloqueantes del plan F2
|--------------------------------------------------------------------------
| Uso (desde el worktree, contra la base de ENSAYO):
|   DB_DATABASE=bd_anfeca_ensayo_pruebas_pg php scripts/anfeca/diagnosticar_incidencias.php <plan_f2.json>
|
| - Todo ocurre en una transacción READ ONLY; no escribe nada.
| - No imprime CURP, nombres, correos, calles ni teléfonos: solo IDs, valores de catálogo,
|   patrones y banderas (sí/no) para decidir con evidencia.
| - No propone valores inventados: solo marca "candidato técnico" cuando la corrección es
|   una conversión de formato demostrable (p. ej. '1000022.0' exportado por Excel).
*/

use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$bd = DB::connection()->getDatabaseName();
if ($bd !== 'bd_anfeca_ensayo_pruebas_pg' && getenv('ANFECA_DIAGNOSTICO_BD') !== $bd) {
    fwrite(STDERR, "Ejecútelo contra bd_anfeca_ensayo_pruebas_pg (actual: {$bd}).\n");
    exit(2);
}
$plan = json_decode((string) @file_get_contents($argv[1] ?? ''), true);
if (!is_array($plan)) {
    fwrite(STDERR, "Uso: php scripts/anfeca/diagnosticar_incidencias.php <plan_f2.json>\n");
    exit(2);
}

DB::statement('begin transaction read only');
$si = fn (bool $b) => $b ? 'sí' : 'no';
$prueba = fn (?string $t) => (bool) preg_match('/prueba|ficticio|ficticia|test|demo|ejemplo/i', (string) $t);
$correoPrueba = fn (?string $c) => (bool) preg_match('/@(example\.(test|com|org)|test\.|prueba)/i', (string) $c);

$bloqueantes = array_values(array_filter($plan['incidencias'] ?? [], fn ($i) => $i['bloqueante'] ?? false));
printf("Base: %s · Plan: %s · Bloqueantes: %d\n", $bd, $plan['fecha'] ?? '?', count($bloqueantes));
$porCodigo = [];
foreach ($bloqueantes as $i) {
    $porCodigo[$i['codigo']][] = $i;
}

foreach ($porCodigo as $codigo => $lista) {
    echo "\n=== {$codigo} (".count($lista).")\n";
    foreach ($lista as $i) {
        $id = (int) $i['id'];
        $linea = "#{$id}";

        if ($codigo === 'INSTITUCION_SIN_DATOS_OFICIALES' || $codigo === 'INSTITUCION_INVALIDA') {
            $x = DB::table('public.instituciones_afiliadas')->find($id);
            $usos = DB::table('public.formaciones_academicas_solicitud')->where('institucion_afiliada_id', $id)->count();
            $linea .= sprintf(' · afiliación: %s (%d car.) · nombre: %s · zona: %s · formaciones que la usan: %d',
                $x?->numero_afiliacion === null ? 'nula' : 'presente', mb_strlen((string) $x?->numero_afiliacion),
                $si(filled($x?->nombre)), $si(filled($x?->zona_id) || filled($x?->zona_anfeca ?? null)), $usos);
        } elseif ($codigo === 'ENTIDAD_EN_REVISION') {
            $d = DB::table('public.datos_solicitantes')->find($id);
            $u = DB::table('public.users')->find($d?->user_id);
            $cp = preg_replace('/\D/', '', (string) $d?->codigo_postal);
            $sol = DB::table('public.solicitudes_certificacion')->where('user_id', $d?->user_id);
            $linea .= sprintf(" · entidad: '%s' · municipio = monterrey: %s · CP en rango SEPOMEX Nuevo León (64000–67999): %s"
                .' · señales de prueba (nombre/correo/entidad): %s/%s/%s · solicitudes: %d (enviadas %d) · autorizado: %s',
                $d?->entidad_federativa, $si(\App\Services\Pdm\NormalizadorCatalogos::clave($d?->municipio) === 'monterrey'),
                strlen($cp) === 5 ? $si((int) $cp >= 64000 && (int) $cp <= 67999) : 'CP no válido',
                $si($prueba($d?->nombres.' '.$d?->apellido_paterno)), $si($correoPrueba($u?->email) || $correoPrueba($d?->correo_electronico)),
                $si($prueba($d?->entidad_federativa)), (clone $sol)->count(), (clone $sol)->where('estado', 'enviado')->count(), $si((bool) $d?->datos_autorizados));
        } elseif ($codigo === 'CERTIFICADO_EN_REVISION') {
            $h = DB::table('public.certificados_historicos')->find($id);
            $n = trim((string) $h?->numero_certificado);
            $patron = match (true) {
                $n === '' => 'vacío',
                (bool) preg_match('/^\d{1,7}\.0+$/', $n) => 'decimal de Excel (.0)',
                (bool) preg_match('/^\d+(\.\d+)?[eE][+-]?\d+$/', $n) => 'notación científica',
                (bool) preg_match('/^\d{8,}$/', $n) => 'más de 7 dígitos',
                (bool) preg_match('/^[\d\s\-\.\/]+$/', $n) => 'dígitos con separadores',
                default => 'texto/alfanumérico',
            };
            $candidato = $patron === 'decimal de Excel (.0)' ? preg_replace('/\.0+$/', '', $n) : null;
            $linea .= sprintf(" · número: patrón %s, %d car.%s · años (1ª vez/refrendo 1): %s/%s · usuario vinculado: %s · lo usa una solicitud: %s · lote: %s",
                $patron, mb_strlen($n), $candidato ? " · candidato técnico: '{$candidato}'" : '',
                $h?->primera_vez ?? 'nulo', $h?->refrendo_1 ?? 'nulo', $si($h?->user_id !== null),
                $si(DB::table('public.solicitudes_certificacion')->where('certificado_historico_id', $id)->exists()), $h?->lote_importacion ?? 'nulo');
        } elseif ($codigo === 'SOLICITUD_INVALIDA' || $codigo === 'SOLICITUD_SIN_PERSONA') {
            $s = DB::table('public.solicitudes_certificacion')->find($id);
            $sel = DB::table('public.selecciones_certificacion')->where('user_id', $s?->user_id)->first();
            $linea .= sprintf(" · trámite: %s · certificación: %s · estado: %s · fecha_envio: %s · compromiso: %s/%s · área: '%s' · tipo: '%s'"
                ." · selección del usuario: %s (misma certificación: %s; área '%s', tipo '%s')",
                $s?->tipo_tramite, $s?->certificacion, $s?->estado, $si($s?->fecha_envio !== null),
                $si((bool) $s?->compromiso_aceptado), $si($s?->compromiso_aceptado_en !== null),
                $s?->area_certificacion, $s?->tipo_personal, $si($sel !== null), $si($sel?->certificacion === $s?->certificacion),
                $sel?->area_certificacion, $sel?->tipo_personal);
        }

        echo $linea."\n   Motivo del plan: ".($i['mensaje'] ?? '')."\n";
    }
}
DB::statement('rollback');
