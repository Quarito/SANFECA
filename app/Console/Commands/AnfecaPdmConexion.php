<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * SOLO LECTURA. Muestra a qué base se conecta Laravel EN REALIDAD antes de cualquier
 * operación de F1/F2/F3, y revisa las líneas DB_* del .env (sin mostrar la contraseña).
 *
 * Explica por qué un "grep DB_DATABASE .env" puede no mostrar una línea que sí existe:
 * BOM, retorno de carro (CRLF), espacios iniciales, prefijo "export", duplicados, o una
 * variable del entorno de la sesión que tiene prioridad sobre .env.
 */
class AnfecaPdmConexion extends Command
{
    protected $signature = 'anfeca:pdm-conexion {--sin-consulta : No conectarse; solo revisar configuración y .env}';

    protected $description = 'Muestra la conexión efectiva de Laravel y revisa las variables DB_* (solo lectura)';

    private const SECRETAS = ['DB_PASSWORD', 'DB_URL'];

    private const CLAVES = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_URL'];

    public function handle(): int
    {
        $conexion = config('database.default');
        $cfg = (array) config("database.connections.{$conexion}");

        $this->info('Configuración efectiva de Laravel');
        $this->table(['Parámetro', 'Valor'], [
            ['Conexión por omisión', $conexion],
            ['Host', $cfg['host'] ?? '—'],
            ['Puerto', $cfg['port'] ?? '—'],
            ['Base', $cfg['database'] ?? '—'],
            ['Usuario', $cfg['username'] ?? '—'],
            ['Contraseña', filled($cfg['password'] ?? null) ? '(definida, no se muestra)' : '(vacía)'],
            ['DB_URL', filled($cfg['url'] ?? null) ? 'DEFINIDA: tiene prioridad sobre host/base' : '(vacía)'],
            ['Configuración en caché', $this->laravel->configurationIsCached() ? 'SÍ: .env y variables se ignoran (php artisan config:clear)' : 'no'],
            ['Modo PDM (config)', (string) config('anfeca_pdm.modo')],
        ]);

        $this->revisarEnv();

        if (!$this->option('sin-consulta')) {
            try {
                $f = DB::selectOne(
                    $conexion === 'pgsql'
                        ? "select current_database() as bd, current_user as usuario, host(inet_server_addr()) as ip, inet_server_port() as puerto, current_setting('server_version') as version,
                           (select count(*) from pg_tables where schemaname = 'anfeca_modelo') as pdm,
                           (select count(*) from pg_tables where schemaname = 'public') as public"
                        : 'select 1 as bd'
                );
                $this->info('Conexión real (consulta al servidor)');
                $this->table(['Dato', 'Valor'], collect((array) $f)->map(fn ($v, $k) => [$k, $v ?? '(socket local)'])->values()->all());

                if ($conexion === 'pgsql' && $f->bd !== ($cfg['database'] ?? null)) {
                    $this->error('La base real difiere de la configurada.');

                    return self::FAILURE;
                }
            } catch (Throwable $e) {
                $this->error('No fue posible conectar: '.strtok($e->getMessage(), "\n"));

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function revisarEnv(): void
    {
        $ruta = $this->laravel->environmentFilePath();
        $filas = [];

        if (!is_readable($ruta)) {
            $this->warn("No se puede leer {$ruta}.");

            return;
        }

        $lineas = file($ruta);
        $vistas = [];

        foreach ($lineas as $n => $linea) {
            $numero = $n + 1;
            $limpia = $n === 0 ? preg_replace('/^\xEF\xBB\xBF/', '', $linea) : $linea;

            foreach (self::CLAVES as $clave) {
                if (!preg_match('/^\s*(export\s+)?'.$clave.'\s*=/', $limpia)) {
                    continue;
                }

                $avisos = [];
                if ($n === 0 && $limpia !== $linea) {
                    $avisos[] = 'BOM al inicio';
                }
                if (str_contains($linea, "\r")) {
                    $avisos[] = 'fin de línea CRLF (\\r)';
                }
                if (preg_match('/^\s+/', $limpia)) {
                    $avisos[] = 'espacios al inicio';
                }
                if (preg_match('/^\s*export\s/', $limpia)) {
                    $avisos[] = 'prefijo export';
                }
                if (isset($vistas[$clave])) {
                    $avisos[] = "duplicada (también en la línea {$vistas[$clave]}; gana la última)";
                }
                $vistas[$clave] = $numero;

                $valor = trim(explode('=', $limpia, 2)[1] ?? '');
                $mostrar = in_array($clave, self::SECRETAS, true) ? ($valor === '' ? '(vacía)' : '(definida)') : trim($valor, "\"' \r\n");

                $filas[] = [$numero, $clave, $mostrar, $avisos === [] ? 'ok' : implode('; ', $avisos)];
            }
        }

        // Entorno ORIGINAL del proceso (antes de cargar .env). getenv() no sirve:
        // Laravel también vuelca ahí los valores de .env.
        $original = $this->entornoOriginal();
        if ($original === null) {
            $filas[] = ['entorno', '—', '—', 'No se pudo leer /proc/self/environ; revise "env | grep ^DB_" en la sesión'];
        } else {
            foreach (self::CLAVES as $clave) {
                if (array_key_exists($clave, $original)) {
                    $filas[] = ['entorno', $clave, in_array($clave, self::SECRETAS, true) ? '(definida)' : $original[$clave],
                        'exportada en la sesión: tiene prioridad sobre .env'];
                }
            }
        }

        $this->info("Variables DB_* en {$ruta} y en la sesión");
        $this->table(['Línea', 'Clave', 'Valor', 'Observación'], $filas);
    }

    /** @return array<string,string>|null */
    private function entornoOriginal(): ?array
    {
        $contenido = @file_get_contents('/proc/self/environ');
        if ($contenido === false) {
            return null;
        }

        $vars = [];
        foreach (explode("\0", $contenido) as $par) {
            if (str_contains($par, '=')) {
                [$k, $v] = explode('=', $par, 2);
                $vars[$k] = $v;
            }
        }

        return $vars;
    }
}
