<?php
/*
 * Proceso independiente para las pruebas de concurrencia REAL en PostgreSQL desechable
 * (tests/Pg/RevisionDocumentalConcurrenciaPgTest.php). No se usa fuera de las pruebas.
 *   modo «revisar»: espera al instante común y llama al servicio RevisionDocumental::registrar().
 *   modo «carga»:   simula al solicitante sustituyendo un archivo con la solicitud bloqueada FOR UPDATE.
 */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Exceptions\Coordinacion\ConflictoRevision;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use App\Services\Coordinacion\RevisionDocumental;
use Illuminate\Support\Facades\DB;

$p = json_decode(base64_decode($argv[1] ?? ''), true, flags: JSON_THROW_ON_ERROR);
if (DB::getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'bd_anfeca_desechable_pruebas_pg') {
    echo json_encode(['resultado' => 'error:SEGURIDAD base no desechable']);
    exit(1);
}
DB::select('select 1');                        // conexión abierta antes del instante común
while (microtime(true) < $p['inicio']) {
    usleep(200);
}

try {
    if ($p['modo'] === 'carga') {
        DB::transaction(function () use ($p) {
            SolicitudCertificacion::query()->whereKey($p['solicitud'])->lockForUpdate()->firstOrFail();
            DB::table('documentos_probatorios_solicitud')->where('solicitud_id', $p['solicitud'])->update([$p['campo'] => $p['ruta_nueva']]);
            usleep((int) ($p['retener'] * 1_000_000));
        });
        echo json_encode(['resultado' => 'cargado', 'fin' => microtime(true)]);
        exit(0);
    }

    [$revision, $creada] = app(RevisionDocumental::class)->registrar(
        User::findOrFail($p['coordinador']), SolicitudCertificacion::findOrFail($p['solicitud']), $p['campo'], $p['datos']);
    echo json_encode(['resultado' => $creada ? 'creada' : 'reintento', 'id' => $revision->id, 'fin' => microtime(true)]);
} catch (ConflictoRevision $e) {
    echo json_encode(['resultado' => 'conflicto:'.$e->motivo, 'fin' => microtime(true)]);
} catch (Throwable $e) {
    echo json_encode(['resultado' => 'error:'.get_class($e).': '.$e->getMessage()]);
}
