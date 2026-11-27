<?php

// Ejecutar con PHP CLI desde el worktree de revisión. Salida sin credenciales ni datos de registros.
$ruta = $argv[1] ?? '';
if (! in_array($ruta, ['/var/www/ANFECA_ENSAYO', '/var/www/anfeca'], true)
    || ! is_file($ruta.'/vendor/autoload.php')) {
    fwrite(STDERR, "Indique una de las instalaciones ANFECA disponibles.\n");
    exit(1);
}
chdir($ruta);
require $ruta.'/vendor/autoload.php';
$app = require $ruta.'/bootstrap/app.php';

try {
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $con = $app->make('db')->connection();
    if ($con->getDriverName() !== 'pgsql') {
        fwrite(STDERR, "La conexión efectiva no es PostgreSQL.\n");
        exit(1);
    }

    $resumen = $con->transaction(function () use ($app, $con) {
        $con->statement('set transaction read only');
        $con->statement("set local statement_timeout = '10s'");
        $db = $con->selectOne("select current_database() as base, current_user as rol,
            host(inet_server_addr()) as host, inet_server_port() as puerto,
            current_setting('server_version') as version, d.oid as oid_base,
            has_database_privilege(current_user, d.oid, 'CREATE') as puede_crear_esquema,
            has_schema_privilege(current_user, 'public', 'CREATE') as puede_crear_en_public,
            (select count(*) from pg_stat_activity where datname = current_database()) as conexiones
            from pg_database d where d.datname = current_database()");
        if ($con->selectOne("select has_function_privilege(current_user, 'pg_catalog.pg_control_system()', 'EXECUTE') as disponible")->disponible) {
            $db->identificador_cluster = $con->selectOne('select system_identifier::text as id from pg_control_system()')->id;
        }

        $inventario = $con->select("select table_schema as esquema, count(*) as tablas from information_schema.tables
            where table_schema in ('public', 'anfeca_modelo', 'anfeca_compat') and table_type='BASE TABLE' group by 1 order by 1");
        $migraciones = $con->selectOne("select to_regclass('public.migrations') is not null as disponible")->disponible
            ? $con->table('public.migrations')->orderBy('id')->pluck('migration')->all()
            : [];
        $pendientes = [];
        foreach (['migrations', 'migrations_pdm', 'migrations_propuestas'] as $grupo) {
            $archivos = glob(database_path($grupo.'/*.php'));
            $pendientes[$grupo] = array_values(array_diff(
                array_map(static fn (string $a) => basename($a, '.php'), $archivos), $migraciones
            ));
        }
        $modoRegistrado = $con->selectOne("select to_regclass('anfeca_modelo.integracion_control') is not null as disponible")->disponible
            ? $con->table('anfeca_modelo.integracion_control')->where('ic_id', 1)->value('ic_modo')
            : null;

        $catalogos = [];
        foreach (['licenciaturas', 'posgrados'] as $tipo) {
            $config = (array) config('anfeca_catalogos.'.$tipo);
            $archivo = $config['ruta'] ?? '';
            $sha = is_readable($archivo) && is_file($archivo) ? hash_file('sha256', $archivo) : null;
            $catalogos[$tipo] = [
                'bandera' => $config['activo'] ?? null,
                'version_configurada' => $config['version'] ?? null,
                'sha256' => $sha,
                'coincide_sha_configurado' => filled($config['sha256'] ?? null) && $sha !== null
                    ? hash_equals(strtolower($config['sha256']), $sha) : null,
            ];
        }

        return [
            'php_cli' => PHP_VERSION,
            'laravel' => $app->version(),
            'configuracion_en_cache' => $app->configurationIsCached(),
            'host_configurado' => $con->getConfig('host'),
            'puerto_configurado' => $con->getConfig('port'),
            'base_configurada_efectiva' => $con->getDatabaseName(),
            'postgresql' => $db,
            'tablas_visibles' => $inventario,
            'migraciones_aplicadas' => $migraciones,
            'migraciones_pendientes_segun_archivos' => $pendientes,
            'pdm_configurado' => config('anfeca_pdm.modo'),
            'pdm_registrado' => $modoRegistrado,
            'sesiones' => config('session.driver'),
            'cache' => config('cache.default'),
            'colas' => config('queue.default'),
            'almacenamiento_local' => config('filesystems.disks.local.root'),
            'catalogos' => $catalogos,
        ];
    });

    echo json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "Inventario Laravel/PostgreSQL incompleto. Revise conectividad y permisos por separado.\n");
    exit(1);
}
