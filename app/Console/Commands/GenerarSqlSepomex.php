<?php

namespace App\Console\Commands;

use App\Services\Anfeca\Sepomex\CatalogoSepomex;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Genera, a partir del archivo oficial SEPOMEX, los scripts SQL que cargan ENTIDAD_FEDERATIVA, ALCALDIA_MUNICIPIO y
 * COLONIA directamente con psql (sin Laravel, PHP ni conexión a SEPOMEX). Usa la misma lectura y consolidación que
 * anfeca:sepomex, por lo que ambos cargan exactamente los mismos registros.
 *
 * Los scripts no contienen identificadores, rutas ni usuarios de ningún ambiente: las referencias se resuelven con los
 * identificadores reales de la base destino mediante claves naturales (nombre de entidad, nombre de municipio dentro de
 * su entidad; municipio + código postal + nombre de colonia).
 *
 * Los archivos generados reproducen el catálogo oficial: son de uso interno y NO deben versionarse en Git.
 */
class GenerarSqlSepomex extends Command
{
    protected $signature = 'anfeca:sepomex-sql
        {archivo : CPdescarga.xls o CPdescarga.txt oficial de Correos de México}
        {--salida= : Directorio NUEVO del paquete (fuera del repositorio o en storage/app/private)}
        {--sha256= : Huella SHA-256 esperada del archivo}
        {--fuente=https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx : Fuente oficial}
        {--fecha-descarga= : Fecha de descarga del archivo, si se conoce (AAAA-MM-DD)}';

    protected $description = 'Genera los scripts SQL (INSERT idempotentes) del catálogo SEPOMEX para cargarlo directamente con psql.';

    private const DE = 'ÁÀÄÂÃÉÈËÊÍÌÏÎÓÒÖÔÕÚÙÜÛÑÇáàäâãéèëêíìïîóòöôõúùüûñç';
    private const A = 'AAAAAEEEEIIIIOOOOOUUUUNCaaaaaeeeeiiiiooooouuuunc';

    private CatalogoSepomex $c;
    private string $sql;

    public function handle(): int
    {
        try {
            return $this->ejecutar();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /** Expresión SQL equivalente a CatalogoSepomex::clave() para los caracteres del catálogo. */
    public static function norm(string $expr): string
    {
        return "lower(btrim(regexp_replace(translate({$expr}, '".self::DE."', '".self::A."'), '\\s+', ' ', 'g')))";
    }

    /** Simulación en PHP de norm() para comprobar que SQL y PHP comparan igual. */
    private static function normPhp(string $s): string
    {
        $mapa = array_combine(mb_str_split(self::DE), str_split(self::A));

        return strtolower(trim(preg_replace('/\s+/u', ' ', strtr($s, $mapa)), ' '));
    }

    private static function q(string $s): string
    {
        if (preg_match('/[\x00-\x1F]/', $s) || !mb_check_encoding($s, 'UTF-8')) {
            throw new RuntimeException("Valor no representable: {$s}");
        }

        return "'".str_replace("'", "''", $s)."'";
    }

    private function ejecutar(): int
    {
        if ((int) ini_get('memory_limit') > 0 && (int) ini_get('memory_limit') < 1024) {
            ini_set('memory_limit', '1024M');
        }
        $inicio = microtime(true);
        $archivo = (string) $this->argument('archivo');
        $salida = (string) $this->option('salida');
        if ($salida === '') {
            throw new RuntimeException('Indique --salida.');
        }
        if (file_exists($salida) && (new \FilesystemIterator($salida))->valid()) {
            throw new RuntimeException("{$salida} ya existe y no está vacío: no se sobrescribe.");
        }
        @mkdir($salida, 0750, true);
        $real = realpath($salida);
        $base = realpath(base_path());
        if ($real === false || (str_starts_with($real.'/', $base.'/') && !str_starts_with($real.'/', $base.'/storage/app/private/'))) {
            throw new RuntimeException('El paquete reproduce el catálogo oficial: genérelo fuera del repositorio o en storage/app/private.');
        }

        $this->info('Leyendo y validando el archivo oficial…');
        $this->c = $c = CatalogoSepomex::leer($archivo);
        if ($this->option('sha256') && !hash_equals(strtolower((string) $this->option('sha256')), $c->sha256)) {
            throw new RuntimeException("La huella del archivo ({$c->sha256}) no coincide con --sha256.");
        }
        foreach (array_merge($c->estados, $c->municipios) as $n) {
            if (self::normPhp($n) !== CatalogoSepomex::clave($n)) {
                throw new RuntimeException("La normalización SQL no es equivalente para «{$n}»: amplíe la tabla de caracteres.");
            }
        }

        // Agrupación: municipios por entidad, colonias por municipio (orden determinista, comparación por bytes).
        $munPorEntidad = $colPorMunicipio = [];
        foreach ($c->municipios as $m => $nombre) {
            $munPorEntidad[(int) explode('|', $m)[0]][$m] = $nombre;
        }
        foreach ($c->colonias as [$m, $cp, $nombre]) {
            $colPorMunicipio[$m][] = [$cp, $nombre];
        }
        foreach ($munPorEntidad as &$lista) {
            uasort($lista, 'strcmp');
        }
        unset($lista);
        foreach ($colPorMunicipio as &$lista) {
            usort($lista, fn ($a, $b) => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));
        }
        unset($lista);

        $this->sql = $salida.'/sql';
        @mkdir($this->sql, 0750, true);
        @mkdir($salida.'/evidencias', 0750, true);
        $huellas = $this->huellasPorEntidad($munPorEntidad, $colPorMunicipio);

        $this->escribir('00_diagnostico_previo.sql', $this->diagnostico($munPorEntidad));
        $this->escribir('01_insert_entidad_federativa.sql', $this->envolver('01', $this->entidades()));
        $this->escribirInsertsMunicipios($munPorEntidad);
        $this->escribirInsertsColonias($munPorEntidad, $colPorMunicipio);
        $this->escribir('04_verificacion_catalogos.sql', $this->verificacion($huellas));
        $this->escribir('05_reversion_catalogos.sql', $this->reversion());
        $this->escribir('SEPOMEX_CATALOGO_COMPLETO.sql', $this->maestro());

        // Evidencias derivadas del archivo.
        $c->escribirConsolidados($salida.'/evidencias/reporte_duplicados.csv');
        $f = fopen($salida.'/evidencias/conteos_por_entidad.csv', 'wb');
        fputcsv($f, ['entidad_clave', 'entidad', 'municipios', 'colonias', 'codigos_postales', 'registros_consolidados', 'huella_municipios_md5', 'huella_colonias_md5'], escape: '');
        foreach ($c->conteosPorEntidad() as $e => $x) {
            fputcsv($f, [sprintf('%02d', $e), $x['nombre'], $x['municipios'], $x['colonias'], $x['codigos_postales'], $x['consolidados'], $huellas[$e]['municipios'], $huellas[$e]['colonias']], escape: '');
        }
        fclose($f);

        $cps = count(array_unique(array_column($c->colonias, 1)));
        $resumen = [
            'archivo' => basename($archivo),
            'fuente_oficial' => (string) $this->option('fuente'),
            'version_sepomex' => $c->version,
            'fecha_descarga' => $this->option('fecha-descarga') ?: null,
            'sha256' => $c->sha256,
            'registros_originales' => $c->filas,
            'registros_procesados' => count($c->colonias),
            'registros_consolidados' => $c->repetidos,
            'colonias_con_registros_consolidados' => count($c->consolidados()),
            'nombres_con_variante_de_acentos_o_mayusculas' => $c->grafias,
            'entidades' => count($c->estados),
            'municipios' => count($c->municipios),
            'colonias' => count($c->colonias),
            'total_registros_tres_tablas' => count($c->estados) + count($c->municipios) + count($c->colonias),
            'codigos_postales_distintos' => $cps,
            'codigos_postales_con_cero_inicial' => count(array_filter(array_unique(array_column($c->colonias, 1)), fn ($cp) => $cp[0] === '0')),
            'inconsistencias' => [
                'estructura' => 'ninguna (encabezado oficial, claves numéricas, longitudes dentro del modelo)',
                'claves_con_dos_nombres' => 0,
                'municipios_que_solo_difieren_por_clave' => 0,
                'tipo_de_asentamiento_no_representable' => "{$c->repetidos} registros que solo difieren en el tipo se consolidan (reporte_duplicados.csv)",
            ],
            'restricciones_de_uso' => 'Correos de México: «se proporciona en forma gratuita para uso particular, no estando permitida su comercialización, total o parcial, ni su distribución a terceros bajo ningún concepto». Uso interno de ANFECA; no publicar el archivo ni los SQL derivados.',
            'generado' => date('c'),
            'segundos' => round(microtime(true) - $inicio, 1),
            'memoria_pico_mb' => round(memory_get_peak_usage(true) / 1048576),
        ];
        $archivos = [];
        foreach (glob($this->sql.'/*.sql') as $s) {
            $archivos['sql/'.basename($s)] = ['sha256' => hash_file('sha256', $s), 'bytes' => filesize($s)];
        }
        foreach (glob($salida.'/evidencias/*.csv') as $s) {
            $archivos['evidencias/'.basename($s)] = ['sha256' => hash_file('sha256', $s), 'bytes' => filesize($s)];
        }
        file_put_contents($salida.'/manifiesto_generacion.json', json_encode($resumen + ['archivos' => $archivos], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        $this->line(json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info("Paquete SQL generado en {$salida}");

        return self::SUCCESS;
    }

    private function escribir(string $nombre, string $contenido): void
    {
        file_put_contents($this->sql.'/'.$nombre, $contenido);
    }

    private function encabezado(string $titulo, string $detalle): string
    {
        $c = $this->c;

        return "-- =====================================================================================================\n"
            ."-- ANFECA · Catálogo Nacional de Códigos Postales (SEPOMEX) · {$titulo}\n"
            ."-- Archivo oficial: ".basename($c->archivo)." · versión {$c->version} · SHA-256 {$c->sha256}\n"
            ."-- {$detalle}\n"
            ."-- Modelo oficial de 36 tablas: solo entidad_federativa, alcaldia_municipio y colonia del esquema public.\n"
            ."-- Datos de Correos de México: uso interno; prohibida su comercialización o distribución a terceros.\n"
            ."-- Generado por anfeca:sepomex-sql. No editar a mano. Codificación UTF-8.\n"
            ."-- =====================================================================================================\n"
            ."\\set ON_ERROR_STOP on\nSET client_encoding = 'UTF8';\n\n";
    }

    /** Comprobaciones previas comunes, bloqueo y funciones temporales de la sesión (pg_temp: no son parte del esquema). */
    private function preparacion(): string
    {
        $nombres = implode(', ', array_map(fn ($n) => self::q(CatalogoSepomex::clave($n)), $this->c->estados));
        $n = self::norm('enfe_nombre');
        $na = self::norm('almu_nombre');

        return <<<SQL
-- ---- Preparación de la carga (dentro de la transacción) -------------------------------------------------------------
SET LOCAL lock_timeout = '10s';
-- Bloqueo contra cargas simultáneas: se permite consultar el catálogo, no modificarlo, hasta COMMIT o ROLLBACK.
LOCK TABLE public.entidad_federativa, public.alcaldia_municipio, public.colonia IN SHARE ROW EXCLUSIVE MODE;

CREATE FUNCTION pg_temp.sepomex_detener(mensaje text) RETURNS text LANGUAGE plpgsql AS \$f\$
BEGIN RAISE EXCEPTION 'SEPOMEX DETENIDO: %', mensaje; RETURN NULL; END \$f\$;

-- Comprueba cada bloque (todas las filas resueltas exactamente una vez y sin conflictos) y acumula los insertados.
CREATE FUNCTION pg_temp.sepomex_bloque(tabla text, bloque text, esperados bigint, resueltos bigint, conflictos bigint,
                                       insertados bigint, ids text DEFAULT NULL) RETURNS bigint LANGUAGE plpgsql AS \$f\$
BEGIN
    IF resueltos <> esperados THEN
        RAISE EXCEPTION 'SEPOMEX DETENIDO: % «%»: % filas del catálogo resolvieron % referencias (faltante o ambigua).', tabla, bloque, esperados, resueltos;
    END IF;
    IF conflictos > 0 THEN
        RAISE EXCEPTION 'SEPOMEX DETENIDO: % «%»: % nombres existentes difieren del catálogo solo en acentos, mayúsculas o espacios; se requiere revisión.', tabla, bloque, conflictos;
    END IF;
    PERFORM set_config('anfeca_sepomex.' || tabla, (coalesce(nullif(current_setting('anfeca_sepomex.' || tabla, true), ''), '0')::bigint + insertados)::text, true);
    IF ids IS NOT NULL THEN
        PERFORM set_config('anfeca_sepomex.' || tabla || '_ids', concat_ws(',', nullif(current_setting('anfeca_sepomex.' || tabla || '_ids', true), ''), ids), true);
    END IF;
    RETURN insertados;
END \$f\$;

-- Lleva la secuencia SERIAL al máximo existente si quedó atrás. Nunca la disminuye.
CREATE FUNCTION pg_temp.sepomex_secuencia(tabla text, columna text) RETURNS bigint LANGUAGE plpgsql AS \$f\$
DECLARE s text := pg_get_serial_sequence('public.' || tabla, columna); m bigint; u bigint; c boolean;
BEGIN
    EXECUTE format('SELECT max(%I) FROM public.%I', columna, tabla) INTO m;
    EXECUTE format('SELECT last_value, is_called FROM %s', s) INTO u, c;
    IF m IS NOT NULL AND (m > u OR (m = u AND NOT c)) THEN PERFORM setval(s, m); RETURN m; END IF;
    RETURN u;
END \$f\$;

-- Decide cómo se concilia una entidad del catálogo: 1 = ya existe (se reutiliza su identificador real), 0 = se inserta.
CREATE FUNCTION pg_temp.sepomex_entidad(clave integer, nombre text) RETURNS integer LANGUAGE plpgsql AS \$f\$
DECLARE n integer; ocupante text;
BEGIN
    SELECT count(*) INTO n FROM public.entidad_federativa WHERE {$n} = {$this->normLiteral('nombre')};
    IF n > 1 THEN
        RAISE EXCEPTION 'SEPOMEX DETENIDO: la entidad «%» está registrada % veces; se requiere revisión.', nombre, n;
    END IF;
    IF n = 1 THEN RETURN 1; END IF;
    SELECT enfe_nombre INTO ocupante FROM public.entidad_federativa WHERE enfe_id_entidad_federativa = clave;
    IF ocupante IS NOT NULL THEN
        IF NOT ({$this->normLiteral('ocupante')} = ANY (ARRAY[{$nombres}])) THEN
            RAISE EXCEPTION 'SEPOMEX DETENIDO: ENTIDAD_FEDERATIVA % es «%» y SEPOMEX indica «%»; se requiere revisión.', clave, ocupante, nombre;
        END IF;
        PERFORM pg_temp.sepomex_secuencia('entidad_federativa', 'enfe_id_entidad_federativa');
    END IF;
    RETURN 0;
END \$f\$;

-- Validaciones previas: base UTF-8, estructura oficial de las tres tablas y ausencia de duplicados ambiguos.
DO \$v\$
DECLARE n integer; d text;
BEGIN
    IF (SELECT pg_encoding_to_char(encoding) FROM pg_database WHERE datname = current_database()) <> 'UTF8' THEN
        PERFORM pg_temp.sepomex_detener('la base no está en UTF-8.');
    END IF;
    SELECT count(*) INTO n FROM information_schema.columns
     WHERE table_schema = 'public' AND (table_name, column_name, data_type, coalesce(character_maximum_length, 0)) IN (
        ('entidad_federativa', 'enfe_id_entidad_federativa', 'integer', 0), ('entidad_federativa', 'enfe_nombre', 'character varying', 85),
        ('alcaldia_municipio', 'almu_id_alcaldia', 'integer', 0), ('alcaldia_municipio', 'almu_id_entidad_federativa', 'integer', 0),
        ('alcaldia_municipio', 'almu_nombre', 'character varying', 150), ('colonia', 'colo_id_colonia', 'integer', 0),
        ('colonia', 'colo_id_alcaldia', 'integer', 0), ('colonia', 'colo_nombre', 'character varying', 150),
        ('colonia', 'colo_codigo_postal', 'character varying', 5));
    IF n <> 9 THEN
        PERFORM pg_temp.sepomex_detener('las tablas entidad_federativa, alcaldia_municipio y colonia no tienen la estructura oficial.');
    END IF;
    SELECT string_agg(format('%s (entidad %s)', nombre, ent), '; ') INTO d FROM (
        SELECT min(almu_nombre) AS nombre, almu_id_entidad_federativa AS ent FROM public.alcaldia_municipio
         GROUP BY almu_id_entidad_federativa, {$na} HAVING count(*) > 1) x;
    IF d IS NOT NULL THEN
        PERFORM pg_temp.sepomex_detener('ALCALDIA_MUNICIPIO con nombres duplicados en la base: ' || d);
    END IF;
END \$v\$;

SELECT pg_temp.sepomex_secuencia('alcaldia_municipio', 'almu_id_alcaldia') AS secuencia_municipio,
       pg_temp.sepomex_secuencia('colonia', 'colo_id_colonia') AS secuencia_colonia \gset
-- Marcas para una reversión controlada: los registros nuevos tendrán identificadores mayores que estos máximos.
SELECT coalesce((SELECT max(almu_id_alcaldia) FROM public.alcaldia_municipio), 0) AS municipio_desde,
       coalesce((SELECT max(colo_id_colonia) FROM public.colonia), 0) AS colonia_desde,
       (SELECT count(*) FROM public.domicilio) AS domicilios_antes,
       (SELECT md5(coalesce(string_agg(d::text, E'\\n' ORDER BY d.domi_id_domicilio), '')) FROM public.domicilio d) AS domicilios_huella_antes \gset
-- ---------------------------------------------------------------------------------------------------------------------

SQL;
    }

    private function normLiteral(string $expr): string
    {
        return self::norm($expr);
    }

    /** Envoltura de 01–03: transacción propia cuando se ejecutan solos; dentro del maestro, la del maestro. */
    private function envolver(string $paso, string $cuerpo, string $tabla = 'entidad_federativa'): string
    {
        $titulos = ['01' => 'Paso 1 de 3 · ENTIDAD_FEDERATIVA', '02' => 'Paso 2 de 3 · ALCALDIA_MUNICIPIO', '03' => 'Paso 3 de 3 · COLONIA'];

        return $this->encabezado($titulos[$paso], 'Inserta solo lo faltante; reutiliza los identificadores reales; ejecutable de nuevo sin duplicar.')
            ."\\if :{?sepomex_maestro}\n\\else\n\\echo '== {$titulos[$paso]} (transacción propia)'\nBEGIN ISOLATION LEVEL REPEATABLE READ;\n"
            .$this->preparacion()."\\endif\n\n"
            .$cuerpo
            ."\n\\if :{?sepomex_maestro}\n\\else\n"
            ."SELECT coalesce(nullif(current_setting('anfeca_sepomex.{$tabla}', true), ''), '0') AS insertados_{$tabla} \\gset\n"
            ."\\echo 'Insertados en {$tabla}:' :insertados_{$tabla}\n"
            .$this->marcas()
            ."COMMIT;\n\\endif\n";
    }

    private function marcas(): string
    {
        return "SELECT coalesce(nullif(current_setting('anfeca_sepomex.entidad_federativa_ids', true), ''), 'ninguna') AS entidades_nuevas,\n"
            ."       coalesce((SELECT max(almu_id_alcaldia) FROM public.alcaldia_municipio), 0) AS municipio_hasta,\n"
            ."       coalesce((SELECT max(colo_id_colonia) FROM public.colonia), 0) AS colonia_hasta \\gset\n"
            ."\\echo 'Marcas de reversión (conserve esta línea): -v entidades=':entidades_nuevas' -v municipio_desde=':municipio_desde' -v municipio_hasta=':municipio_hasta' -v colonia_desde=':colonia_desde' -v colonia_hasta=':colonia_hasta\n";
    }

    private function entidades(): string
    {
        $s = "-- 32 entidades federativas. Se concilian por nombre; una nueva toma la clave oficial si está libre.\n";
        foreach ($this->c->estados as $id => $nombre) {
            $q = self::q($nombre);
            $s .= "\n-- ".sprintf('%02d', $id)." · {$nombre}\n"
                ."WITH i AS (\n    INSERT INTO public.entidad_federativa (enfe_id_entidad_federativa, enfe_nombre)\n"
                ."    SELECT CASE WHEN NOT EXISTS (SELECT 1 FROM public.entidad_federativa WHERE enfe_id_entidad_federativa = {$id}) THEN {$id}\n"
                ."                ELSE nextval(pg_get_serial_sequence('public.entidad_federativa', 'enfe_id_entidad_federativa')) END, {$q}\n"
                ."     WHERE pg_temp.sepomex_entidad({$id}, {$q}) = 0\n    RETURNING enfe_id_entidad_federativa AS id)\n"
                ."SELECT pg_temp.sepomex_bloque('entidad_federativa', {$q}, 1, 1, 0, (SELECT count(*) FROM i), (SELECT string_agg(id::text, ',') FROM i)) AS insertados \\gset\n";
        }
        $s .= "\nSELECT pg_temp.sepomex_secuencia('entidad_federativa', 'enfe_id_entidad_federativa') AS secuencia_entidad \\gset\n";
        $nombres = implode(', ', array_map(fn ($n) => self::q(CatalogoSepomex::clave($n)), $this->c->estados));
        $s .= "-- Las 32 entidades del catálogo quedan registradas exactamente una vez.\n"
            ."SELECT CASE WHEN count(DISTINCT ".self::norm('enfe_nombre').") = 32 AND count(*) = 32 THEN 'ok'\n"
            ."            ELSE pg_temp.sepomex_detener('las 32 entidades no quedaron registradas una sola vez') END AS entidades_ok\n"
            ."  FROM public.entidad_federativa WHERE ".self::norm('enfe_nombre')." = ANY (ARRAY[{$nombres}]) \\gset\n";

        return $s;
    }

    private function escribirInsertsMunicipios(array $munPorEntidad): void
    {
        $s = "-- {$this->total($munPorEntidad)} alcaldías y municipios, una instrucción por entidad. La entidad se resuelve por nombre en la\n"
            ."-- base destino; un municipio existente con el mismo nombre se reutiliza (no se duplica ni se modifica).\n";
        foreach ($munPorEntidad as $e => $lista) {
            $ent = $this->c->estados[$e];
            $valores = implode(",\n           ", array_map(fn ($n) => '('.self::q($n).', '.self::q(CatalogoSepomex::clave($n)).')', $lista));
            $n = count($lista);
            $s .= "\n-- ".sprintf('%02d', $e)." · {$ent} · {$n} municipios\n"
                ."WITH v(municipio, clave) AS (\n    VALUES {$valores}),\n"
                ."e AS (SELECT enfe_id_entidad_federativa AS id FROM public.entidad_federativa WHERE ".self::norm('enfe_nombre').' = '.self::q(CatalogoSepomex::clave($ent))."),\n"
                ."r AS (SELECT v.municipio, e.id,\n"
                ."             (SELECT count(*) FROM public.alcaldia_municipio a WHERE a.almu_id_entidad_federativa = e.id\n"
                ."                 AND ".self::norm('a.almu_nombre')." = v.clave AND a.almu_nombre <> v.municipio) AS variantes\n"
                ."        FROM v CROSS JOIN e),\n"
                ."i AS (INSERT INTO public.alcaldia_municipio (almu_id_entidad_federativa, almu_nombre)\n"
                ."      SELECT r.id, r.municipio FROM r WHERE r.variantes = 0 AND NOT EXISTS (\n"
                ."             SELECT 1 FROM public.alcaldia_municipio a WHERE a.almu_id_entidad_federativa = r.id AND a.almu_nombre = r.municipio)\n"
                ."      RETURNING 1)\n"
                ."SELECT pg_temp.sepomex_bloque('alcaldia_municipio', ".self::q($ent).", {$n}, (SELECT count(*) FROM r),\n"
                ."       (SELECT count(*) FROM r WHERE variantes > 0), (SELECT count(*) FROM i)) AS insertados \\gset\n";
        }
        $this->escribir('02_insert_alcaldia_municipio.sql', $this->envolver('02', $s, 'alcaldia_municipio'));
    }

    private function escribirInsertsColonias(array $munPorEntidad, array $colPorMunicipio): void
    {
        $f = fopen($this->sql.'/03_insert_colonia.sql', 'wb');
        $envoltura = $this->envolver('03', "\0", 'colonia');
        [$antes, $despues] = explode("\0", $envoltura);
        fwrite($f, $antes);
        fwrite($f, '-- '.count($this->c->colonias)." colonias y asentamientos, una instrucción por municipio. El municipio se resuelve por su\n"
            ."-- nombre dentro de su entidad; una colonia existente (mismo municipio, código postal y nombre) no se duplica.\n");
        foreach ($munPorEntidad as $e => $lista) {
            $ent = $this->c->estados[$e];
            $claveEnt = self::q(CatalogoSepomex::clave($ent));
            foreach ($lista as $m => $mun) {
                $cols = $colPorMunicipio[$m] ?? [];
                if (!$cols) {
                    continue;
                }
                $n = count($cols);
                $valores = implode(",\n           ", array_map(fn ($x) => '('.self::q($x[1]).', '.self::q($x[0]).')', $cols));
                $etiqueta = self::q("{$ent} · {$mun}");
                fwrite($f, "\n-- ".sprintf('%02d', $e)." · {$ent} · {$mun} · {$n} colonias\n"
                    ."WITH v(colonia, cp) AS (\n    VALUES {$valores}),\n"
                    ."m AS (SELECT a.almu_id_alcaldia AS id FROM public.alcaldia_municipio a\n"
                    ."        JOIN public.entidad_federativa e ON e.enfe_id_entidad_federativa = a.almu_id_entidad_federativa\n"
                    ."       WHERE ".self::norm('e.enfe_nombre')." = {$claveEnt} AND a.almu_nombre = ".self::q($mun)."),\n"
                    ."r AS (SELECT v.colonia, v.cp, m.id FROM v CROSS JOIN m),\n"
                    ."i AS (INSERT INTO public.colonia (colo_id_alcaldia, colo_nombre, colo_codigo_postal)\n"
                    ."      SELECT r.id, r.colonia, r.cp FROM r WHERE NOT EXISTS (\n"
                    ."             SELECT 1 FROM public.colonia c WHERE c.colo_id_alcaldia = r.id AND c.colo_codigo_postal = r.cp AND c.colo_nombre = r.colonia)\n"
                    ."      RETURNING 1)\n"
                    ."SELECT pg_temp.sepomex_bloque('colonia', {$etiqueta}, {$n}, (SELECT count(*) FROM r), 0, (SELECT count(*) FROM i)) AS insertados \\gset\n");
            }
        }
        fwrite($f, $despues);
        fclose($f);
    }

    private function total(array $agrupado): int
    {
        return array_sum(array_map('count', $agrupado));
    }

    /** Huellas md5 por entidad de los municipios y de las colonias (orden por bytes), para comparar con la base. */
    private function huellasPorEntidad(array $munPorEntidad, array $colPorMunicipio): array
    {
        $h = [];
        foreach ($munPorEntidad as $e => $lista) {
            $filas = [];
            foreach ($lista as $m => $mun) {
                foreach ($colPorMunicipio[$m] ?? [] as [$cp, $col]) {
                    $filas[] = [$mun, $cp, $col];
                }
            }
            usort($filas, fn ($a, $b) => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]) ?: strcmp($a[2], $b[2]));
            $nombres = array_values($lista);
            sort($nombres, SORT_STRING);
            $h[$e] = [
                'municipios' => md5(implode("\n", $nombres)),
                'colonias' => md5(implode("\n", array_map(fn ($x) => implode("\t", $x), $filas))),
            ];
        }

        return $h;
    }

    private function valoresEntidades(array $extra): string
    {
        $filas = [];
        foreach ($this->c->conteosPorEntidad() as $e => $x) {
            $filas[] = '('.$e.', '.self::q($x['nombre']).', '.self::q(CatalogoSepomex::clave($x['nombre'])).', '.implode(', ', $extra[$e]).')';
        }

        return implode(",\n        ", $filas);
    }

    private function diagnostico(array $munPorEntidad): string
    {
        $N = self::normas();
        $ent = $this->valoresEntidades(array_map(fn ($x) => [$x['municipios']], $this->c->conteosPorEntidad()));
        $mun = [];
        foreach ($munPorEntidad as $e => $lista) {
            foreach ($lista as $nombre) {
                $mun[] = '('.self::q(CatalogoSepomex::clave($this->c->estados[$e])).', '.self::q($nombre).', '.self::q(CatalogoSepomex::clave($nombre)).')';
            }
        }
        $mun = implode(",\n        ", $mun);

        return $this->encabezado('00 · Diagnóstico previo (SOLO LECTURA)', 'Transacción de solo lectura que termina en ROLLBACK: no modifica nada.')
            .<<<SQL
BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY;
\\pset footer off

\\echo '== 1. Base de datos, esquema y permisos'
SELECT current_database() AS base, current_user AS usuario, current_setting('server_version') AS postgresql,
       pg_encoding_to_char(encoding) AS codificacion, current_schemas(false) AS esquemas,
       has_database_privilege(current_database(), 'TEMP') AS puede_crear_temporales,
       pg_size_pretty(pg_database_size(current_database())) AS tamano_base
  FROM pg_database WHERE datname = current_database();

\\echo '== 2. Existencia y estructura de las tres tablas (9 atributos oficiales)'
SELECT t AS tabla, to_regclass('public.' || t) IS NOT NULL AS existe,
       (SELECT count(*) FROM information_schema.columns c WHERE c.table_schema = 'public' AND c.table_name = t) AS atributos
  FROM unnest(ARRAY['entidad_federativa', 'alcaldia_municipio', 'colonia', 'domicilio']) t;
SELECT count(*) AS tablas_en_public FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';

\\echo '== 3. Registros e identificadores existentes'
SELECT 'entidad_federativa' AS tabla, count(*) AS registros, min(enfe_id_entidad_federativa) AS id_min, max(enfe_id_entidad_federativa) AS id_max FROM public.entidad_federativa
UNION ALL SELECT 'alcaldia_municipio', count(*), min(almu_id_alcaldia), max(almu_id_alcaldia) FROM public.alcaldia_municipio
UNION ALL SELECT 'colonia', count(*), min(colo_id_colonia), max(colo_id_colonia) FROM public.colonia
UNION ALL SELECT 'domicilio', count(*), min(domi_id_domicilio), max(domi_id_domicilio) FROM public.domicilio;

\\echo '== 4. Secuencias SERIAL (último valor frente al máximo; «atrasada» se corrige hacia arriba al cargar)'
SELECT 'entidad_federativa' AS tabla, s.last_value, s.is_called, (SELECT max(enfe_id_entidad_federativa) FROM public.entidad_federativa) AS maximo
  FROM public.entidad_federativa_enfe_id_entidad_federativa_seq s
UNION ALL SELECT 'alcaldia_municipio', s.last_value, s.is_called, (SELECT max(almu_id_alcaldia) FROM public.alcaldia_municipio) FROM public.alcaldia_municipio_almu_id_alcaldia_seq s
UNION ALL SELECT 'colonia', s.last_value, s.is_called, (SELECT max(colo_id_colonia) FROM public.colonia) FROM public.colonia_colo_id_colonia_seq s;

\\echo '== 5. Duplicados e inconsistencias en la base (deben ser 0; los duplicados de municipios detienen la carga)'
SELECT 'entidades con el mismo nombre normalizado' AS revision, count(*) AS casos FROM (
       SELECT 1 FROM public.entidad_federativa GROUP BY {$N['enfe_nombre']} HAVING count(*) > 1) x
UNION ALL SELECT 'municipios repetidos en su entidad', count(*) FROM (
       SELECT 1 FROM public.alcaldia_municipio GROUP BY almu_id_entidad_federativa, {$N['almu_nombre']} HAVING count(*) > 1) x
UNION ALL SELECT 'colonias repetidas (municipio, CP, nombre)', count(*) FROM (
       SELECT 1 FROM public.colonia GROUP BY colo_id_alcaldia, colo_codigo_postal, colo_nombre HAVING count(*) > 1) x
UNION ALL SELECT 'códigos postales inválidos (no son 5 dígitos)', count(*) FROM public.colonia WHERE colo_codigo_postal !~ '^[0-9]{5}$'
UNION ALL SELECT 'nombres con espacios al inicio o al final', (SELECT count(*) FROM public.entidad_federativa WHERE enfe_nombre <> btrim(enfe_nombre))
       + (SELECT count(*) FROM public.alcaldia_municipio WHERE almu_nombre <> btrim(almu_nombre)) + (SELECT count(*) FROM public.colonia WHERE colo_nombre <> btrim(colo_nombre))
UNION ALL SELECT 'municipios sin entidad', count(*) FROM public.alcaldia_municipio a WHERE NOT EXISTS (
       SELECT 1 FROM public.entidad_federativa e WHERE e.enfe_id_entidad_federativa = a.almu_id_entidad_federativa)
UNION ALL SELECT 'colonias sin municipio', count(*) FROM public.colonia c WHERE NOT EXISTS (
       SELECT 1 FROM public.alcaldia_municipio a WHERE a.almu_id_alcaldia = c.colo_id_alcaldia);

\\echo '== 6. Conciliación de las 32 entidades del catálogo con la base'
WITH cat(clave, nombre, normal, municipios) AS (VALUES
        {$ent}),
x AS (SELECT cat.*, (SELECT count(*) FROM public.entidad_federativa e WHERE {$N['e.enfe_nombre']} = cat.normal) AS coincidencias,
             (SELECT min(e.enfe_id_entidad_federativa) FROM public.entidad_federativa e WHERE {$N['e.enfe_nombre']} = cat.normal) AS id_actual,
             (SELECT e.enfe_nombre FROM public.entidad_federativa e WHERE e.enfe_id_entidad_federativa = cat.clave) AS ocupante
        FROM cat)
SELECT lpad(clave::text, 2, '0') AS clave, nombre,
       CASE WHEN coincidencias > 1 THEN 'CONFLICTO: registrada ' || coincidencias || ' veces'
            WHEN coincidencias = 1 THEN 'existe (id ' || id_actual || ')'
            WHEN ocupante IS NULL THEN 'se insertará con id ' || clave
            WHEN {$N['ocupante']} IN (SELECT normal FROM cat) THEN 'se insertará con el siguiente valor de la secuencia'
            ELSE 'CONFLICTO: el id ' || clave || ' es «' || ocupante || '»' END AS resultado
  FROM x ORDER BY clave;
SELECT enfe_id_entidad_federativa AS id, enfe_nombre AS entidad_ajena_al_catalogo FROM public.entidad_federativa e
 WHERE {$N['e.enfe_nombre']} NOT IN (SELECT {$N['nombre']} FROM (VALUES {$this->listaNombres()}) v(nombre));

\\echo '== 7. Alcaldías y municipios: pendientes y conflictos por entidad'
WITH cat(entidad, municipio, normal) AS (VALUES
        {$mun}),
x AS (SELECT cat.entidad, e.enfe_nombre,
             EXISTS (SELECT 1 FROM public.alcaldia_municipio a WHERE a.almu_id_entidad_federativa = e.enfe_id_entidad_federativa AND a.almu_nombre = cat.municipio) AS existe,
             EXISTS (SELECT 1 FROM public.alcaldia_municipio a WHERE a.almu_id_entidad_federativa = e.enfe_id_entidad_federativa
                        AND {$N['a.almu_nombre']} = cat.normal AND a.almu_nombre <> cat.municipio) AS variante
        FROM cat LEFT JOIN public.entidad_federativa e ON {$N['e.enfe_nombre']} = cat.entidad)
SELECT coalesce(enfe_nombre, entidad || ' (entidad aún no registrada)') AS entidad, count(*) AS catalogo,
       count(*) FILTER (WHERE existe) AS existentes, count(*) FILTER (WHERE NOT existe AND NOT variante) AS por_insertar,
       count(*) FILTER (WHERE variante) AS conflictos_de_grafia
  FROM x GROUP BY 1 ORDER BY 1;

\\echo '== 8. Colonias existentes por entidad (las combinaciones municipio + CP + nombre faltantes se insertarán)'
SELECT e.enfe_nombre AS entidad, count(c.*) AS colonias, count(DISTINCT c.colo_codigo_postal) AS codigos_postales
  FROM public.entidad_federativa e JOIN public.alcaldia_municipio a ON a.almu_id_entidad_federativa = e.enfe_id_entidad_federativa
  LEFT JOIN public.colonia c ON c.colo_id_alcaldia = a.almu_id_alcaldia GROUP BY 1 ORDER BY 1;

\\echo '== 9. Catálogo por importar: {$this->resumenCatalogo()}'
ROLLBACK;
\\pset footer on

SQL;
    }

    private static function normas(): array
    {
        $r = [];
        foreach (['enfe_nombre', 'almu_nombre', 'e.enfe_nombre', 'a.almu_nombre', 'ocupante', 'nombre'] as $x) {
            $r[$x] = self::norm($x);
        }

        return $r;
    }

    private function listaNombres(): string
    {
        return implode(', ', array_map(fn ($n) => '('.self::q($n).')', $this->c->estados));
    }

    private function resumenCatalogo(): string
    {
        return count($this->c->estados).' entidades, '.count($this->c->municipios).' municipios, '.count($this->c->colonias)
            .' colonias, '.$this->c->repetidos.' registros consolidados';
    }

    private function verificacion(array $huellas): string
    {
        $N = self::normas();
        $ext = [];
        foreach ($this->c->conteosPorEntidad() as $e => $x) {
            $ext[$e] = [$x['municipios'], $x['colonias'], $x['codigos_postales'], self::q($huellas[$e]['municipios']), self::q($huellas[$e]['colonias'])];
        }
        $ent = $this->valoresEntidades($ext);
        $tm = count($this->c->municipios);
        $tc = count($this->c->colonias);
        $tcp = count(array_unique(array_column($this->c->colonias, 1)));

        return $this->encabezado('04 · Verificación de los catálogos (SOLO LECTURA)', 'Comprueba la carga contra los valores del archivo oficial; termina en ROLLBACK.')
            .<<<SQL
BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY;
\\pset footer off

\\echo '== 1. Totales (esperado del archivo oficial: 32 entidades, {$tm} municipios, {$tc} colonias, {$tcp} códigos postales)'
SELECT (SELECT count(*) FROM public.entidad_federativa) AS entidades, (SELECT count(*) FROM public.alcaldia_municipio) AS municipios,
       (SELECT count(*) FROM public.colonia) AS colonias, (SELECT count(DISTINCT colo_codigo_postal) FROM public.colonia) AS codigos_postales,
       (SELECT count(*) FROM public.entidad_federativa) + (SELECT count(*) FROM public.alcaldia_municipio) + (SELECT count(*) FROM public.colonia) AS total_tres_tablas;

\\echo '== 2. Cobertura por entidad: conteos y huella exacta del catálogo (coincide = la entidad contiene exactamente el catálogo)'
WITH cat(clave, nombre, normal, municipios, colonias, cps, huella_mun, huella_col) AS (VALUES
        {$ent}),
b AS (SELECT cat.*, e.enfe_id_entidad_federativa AS id FROM cat LEFT JOIN public.entidad_federativa e ON {$N['e.enfe_nombre']} = cat.normal),
r AS (SELECT b.*,
             (SELECT count(*) FROM public.alcaldia_municipio a WHERE a.almu_id_entidad_federativa = b.id) AS mun_base,
             (SELECT count(*) FROM public.colonia c JOIN public.alcaldia_municipio a ON a.almu_id_alcaldia = c.colo_id_alcaldia WHERE a.almu_id_entidad_federativa = b.id) AS col_base,
             (SELECT count(DISTINCT c.colo_codigo_postal) FROM public.colonia c JOIN public.alcaldia_municipio a ON a.almu_id_alcaldia = c.colo_id_alcaldia WHERE a.almu_id_entidad_federativa = b.id) AS cps_base,
             (SELECT md5(coalesce(string_agg(a.almu_nombre, E'\\n' ORDER BY a.almu_nombre COLLATE "C"), '')) FROM public.alcaldia_municipio a WHERE a.almu_id_entidad_federativa = b.id) AS h_mun,
             (SELECT md5(coalesce(string_agg(a.almu_nombre || E'\\t' || c.colo_codigo_postal || E'\\t' || c.colo_nombre, E'\\n'
                         ORDER BY a.almu_nombre COLLATE "C", c.colo_codigo_postal COLLATE "C", c.colo_nombre COLLATE "C"), ''))
                FROM public.colonia c JOIN public.alcaldia_municipio a ON a.almu_id_alcaldia = c.colo_id_alcaldia WHERE a.almu_id_entidad_federativa = b.id) AS h_col
        FROM b)
SELECT lpad(clave::text, 2, '0') AS clave, nombre, id AS id_base, municipios AS mun_catalogo, mun_base, colonias AS col_catalogo, col_base,
       cps AS cps_catalogo, cps_base,
       CASE WHEN id IS NULL THEN 'FALTA LA ENTIDAD'
            WHEN h_mun = huella_mun AND h_col = huella_col THEN 'coincide'
            WHEN mun_base >= municipios AND col_base >= colonias THEN 'completa, con registros adicionales'
            ELSE 'INCOMPLETA' END AS resultado
  FROM r ORDER BY clave;

\\echo '== 3. Integridad (todas deben ser 0)'
SELECT 'municipios huérfanos' AS revision, count(*) AS casos FROM public.alcaldia_municipio a
       WHERE NOT EXISTS (SELECT 1 FROM public.entidad_federativa e WHERE e.enfe_id_entidad_federativa = a.almu_id_entidad_federativa)
UNION ALL SELECT 'colonias huérfanas', count(*) FROM public.colonia c WHERE NOT EXISTS (SELECT 1 FROM public.alcaldia_municipio a WHERE a.almu_id_alcaldia = c.colo_id_alcaldia)
UNION ALL SELECT 'domicilios con entidad inexistente', count(*) FROM public.domicilio d
       WHERE NOT EXISTS (SELECT 1 FROM public.entidad_federativa e WHERE e.enfe_id_entidad_federativa = d.domi_id_entidad_federativa)
UNION ALL SELECT 'entidades duplicadas', count(*) FROM (SELECT 1 FROM public.entidad_federativa GROUP BY {$N['enfe_nombre']} HAVING count(*) > 1) x
UNION ALL SELECT 'municipios duplicados en su entidad', count(*) FROM (SELECT 1 FROM public.alcaldia_municipio GROUP BY almu_id_entidad_federativa, {$N['almu_nombre']} HAVING count(*) > 1) x
UNION ALL SELECT 'colonias duplicadas (municipio, CP, nombre)', count(*) FROM (SELECT 1 FROM public.colonia GROUP BY colo_id_alcaldia, colo_codigo_postal, colo_nombre HAVING count(*) > 1) x
UNION ALL SELECT 'códigos postales inválidos', count(*) FROM public.colonia WHERE colo_codigo_postal !~ '^[0-9]{5}$'
UNION ALL SELECT 'códigos postales en más de una entidad', count(*) FROM (SELECT c.colo_codigo_postal FROM public.colonia c
       JOIN public.alcaldia_municipio a ON a.almu_id_alcaldia = c.colo_id_alcaldia GROUP BY 1 HAVING count(DISTINCT a.almu_id_entidad_federativa) > 1) x
UNION ALL SELECT 'llaves foráneas del catálogo no validadas o ausentes', 3 - count(*) FROM pg_constraint
       WHERE contype = 'f' AND convalidated AND conname IN ('fk_alcaldia_contiene_entidad_', 'fk_colonia_se_compon_alcaldia', 'fk_domicili_se_encuen_entidad_')
UNION ALL SELECT 'secuencias por debajo del máximo', count(*) FROM (
       SELECT s.last_value < coalesce((SELECT max(enfe_id_entidad_federativa) FROM public.entidad_federativa), 0) AS atrasada FROM public.entidad_federativa_enfe_id_entidad_federativa_seq s
       UNION ALL SELECT s.last_value < coalesce((SELECT max(almu_id_alcaldia) FROM public.alcaldia_municipio), 0) FROM public.alcaldia_municipio_almu_id_alcaldia_seq s
       UNION ALL SELECT s.last_value < coalesce((SELECT max(colo_id_colonia) FROM public.colonia), 0) FROM public.colonia_colo_id_colonia_seq s) x WHERE atrasada;

\\echo '== 4. Estructura: tablas del esquema public (el modelo oficial tiene 36)'
SELECT count(*) AS tablas FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';

\\echo '== 5. Conservación (solo dentro del script maestro: domicilios antes y después de la carga)'
\\if :{?domicilios_antes}
SELECT :domicilios_antes AS domicilios_antes, (SELECT count(*) FROM public.domicilio) AS domicilios_despues,
       :'domicilios_huella_antes' = (SELECT md5(coalesce(string_agg(d::text, E'\\n' ORDER BY d.domi_id_domicilio), '')) FROM public.domicilio d) AS domicilios_sin_cambios;
\\else
\\echo '   (ejecutado de forma independiente: compare con el conteo del diagnóstico previo)'
SELECT count(*) AS domicilios FROM public.domicilio;
\\endif

\\echo '== 6. Consultas de ejemplo por código postal (incluye ceros iniciales), igual que el servicio interno de ANFECA'
SELECT c.colo_codigo_postal AS cp, e.enfe_nombre AS entidad, a.almu_nombre AS municipio, count(*) AS colonias,
       left(string_agg(c.colo_nombre, ', ' ORDER BY c.colo_nombre), 70) AS primeras_colonias
  FROM public.colonia c JOIN public.alcaldia_municipio a ON a.almu_id_alcaldia = c.colo_id_alcaldia
  JOIN public.entidad_federativa e ON e.enfe_id_entidad_federativa = a.almu_id_entidad_federativa
 WHERE c.colo_codigo_postal IN ('01000', '04510', '06600', '20000', '29000', '44130', '64000', '68000', '77500', '97000', '99999')
 GROUP BY 1, 2, 3 ORDER BY 1;
ROLLBACK;
\\pset footer on

SQL;
    }

    private function reversion(): string
    {
        return $this->encabezado('05 · Reversión controlada (NO se ejecuta automáticamente)', 'Simula por omisión (ROLLBACK); aplica solo con -v confirmar=si.')
            .<<<'SQL'
-- Elimina EXCLUSIVAMENTE los registros que incorporó una carga, identificados por las marcas que imprimió esa carga:
--   psql ... -v entidades=<ids|ninguna> -v municipio_desde=<n> -v municipio_hasta=<n> -v colonia_desde=<n> -v colonia_hasta=<n> \
--            [-v confirmar=si] -f 05_reversion_catalogos.sql
-- Reglas: solo identificadores dentro de los rangos de la carga (los registros previos tienen identificadores menores
-- o no están en la lista); nunca un municipio con colonias que conserve, ni una entidad con municipios o domicilios.
-- Si las marcas no se conservaron o hay dudas, NO use este archivo: restaure el respaldo previo en una base nueva
-- (ver README.md, «Reversión»).
\if :{?municipio_desde}
\else
\echo 'Faltan las marcas de reversión (-v entidades=… -v municipio_desde=… …). No se hace nada.'
\quit
\endif
\if :{?confirmar}
\else
\set confirmar no
\endif
BEGIN;
SET LOCAL lock_timeout = '10s';
LOCK TABLE public.entidad_federativa, public.alcaldia_municipio, public.colonia IN SHARE ROW EXCLUSIVE MODE;
SELECT (:'confirmar' = 'si') AS aplicar, nullif(:'entidades', 'ninguna') IS NOT NULL AS hay_entidades \gset

\echo '== Registros de la carga encontrados'
SELECT (SELECT count(*) FROM public.colonia WHERE colo_id_colonia > :colonia_desde AND colo_id_colonia <= :colonia_hasta) AS colonias,
       (SELECT count(*) FROM public.alcaldia_municipio WHERE almu_id_alcaldia > :municipio_desde AND almu_id_alcaldia <= :municipio_hasta) AS municipios,
       (SELECT count(*) FROM public.entidad_federativa WHERE enfe_id_entidad_federativa = ANY (string_to_array(nullif(:'entidades', 'ninguna'), ',')::int[])) AS entidades;

-- Ninguna tabla del modelo hace referencia a COLONIA.
WITH d AS (DELETE FROM public.colonia WHERE colo_id_colonia > :colonia_desde AND colo_id_colonia <= :colonia_hasta RETURNING 1)
SELECT count(*) AS colonias_eliminadas FROM d;
WITH d AS (DELETE FROM public.alcaldia_municipio a WHERE a.almu_id_alcaldia > :municipio_desde AND a.almu_id_alcaldia <= :municipio_hasta
              AND NOT EXISTS (SELECT 1 FROM public.colonia c WHERE c.colo_id_alcaldia = a.almu_id_alcaldia) RETURNING 1)
SELECT count(*) AS municipios_eliminados FROM d;
WITH d AS (DELETE FROM public.entidad_federativa e WHERE e.enfe_id_entidad_federativa = ANY (string_to_array(nullif(:'entidades', 'ninguna'), ',')::int[])
              AND NOT EXISTS (SELECT 1 FROM public.alcaldia_municipio a WHERE a.almu_id_entidad_federativa = e.enfe_id_entidad_federativa)
              AND NOT EXISTS (SELECT 1 FROM public.domicilio m WHERE m.domi_id_entidad_federativa = e.enfe_id_entidad_federativa) RETURNING 1)
SELECT count(*) AS entidades_eliminadas FROM d;

\echo '== Registros de la carga que se conservan por tener referencias'
SELECT (SELECT count(*) FROM public.alcaldia_municipio WHERE almu_id_alcaldia > :municipio_desde AND almu_id_alcaldia <= :municipio_hasta) AS municipios,
       (SELECT count(*) FROM public.entidad_federativa WHERE enfe_id_entidad_federativa = ANY (string_to_array(nullif(:'entidades', 'ninguna'), ',')::int[])) AS entidades;
\if :aplicar
COMMIT;
\echo 'Reversión APLICADA.'
\else
ROLLBACK;
\echo 'SIMULACIÓN: nada se modificó. Para aplicar, agregue -v confirmar=si.'
\endif

SQL;
    }

    private function maestro(): string
    {
        return $this->encabezado('Script maestro de carga', 'Ejecuta en orden: diagnóstico, entidades, municipios, colonias y verificación, en UNA transacción.')
            .<<<'SQL'
-- Uso (desde el directorio sql/ del paquete):
--   psql -X -v ON_ERROR_STOP=1 -h <servidor> -p <puerto> -U <usuario> -d <base> -f SEPOMEX_CATALOGO_COMPLETO.sql 2>&1 | tee carga_sepomex.log
-- Ante cualquier error psql se detiene y la transacción completa se revierte: no quedan inserciones parciales.
\set sepomex_maestro 1
SELECT clock_timestamp() AS sepomex_inicio \gset

\echo '######## 1/5 · Validaciones previas (solo lectura)'
\ir 00_diagnostico_previo.sql

\echo '######## Inicio de la transacción de carga'
BEGIN ISOLATION LEVEL REPEATABLE READ;
SQL
            ."\n".$this->preparacion()
            .<<<'SQL'
\echo '######## 2/5 · Entidades federativas'
\ir 01_insert_entidad_federativa.sql
\echo '######## 3/5 · Alcaldías y municipios'
\ir 02_insert_alcaldia_municipio.sql
\echo '######## 4/5 · Colonias'
\ir 03_insert_colonia.sql

-- Comprobaciones finales antes de confirmar: domicilios intactos y sin duplicados introducidos.
SELECT coalesce(nullif(current_setting('anfeca_sepomex.entidad_federativa', true), ''), '0') AS insertadas_entidades,
       coalesce(nullif(current_setting('anfeca_sepomex.alcaldia_municipio', true), ''), '0') AS insertados_municipios,
       coalesce(nullif(current_setting('anfeca_sepomex.colonia', true), ''), '0') AS insertadas_colonias \gset
SELECT CASE WHEN (SELECT count(*) FROM public.domicilio) = :domicilios_antes
             AND (SELECT md5(coalesce(string_agg(d::text, E'\n' ORDER BY d.domi_id_domicilio), '')) FROM public.domicilio d) = :'domicilios_huella_antes'
            THEN 'ok' ELSE pg_temp.sepomex_detener('DOMICILIO cambió durante la carga') END AS domicilios_intactos,
       CASE WHEN NOT EXISTS (SELECT 1 FROM public.colonia c WHERE c.colo_id_colonia > :colonia_desde AND EXISTS (
                 SELECT 1 FROM public.colonia d WHERE d.colo_id_alcaldia = c.colo_id_alcaldia AND d.colo_codigo_postal = c.colo_codigo_postal
                    AND d.colo_nombre = c.colo_nombre AND d.colo_id_colonia <> c.colo_id_colonia))
            THEN 'ok' ELSE pg_temp.sepomex_detener('quedaron colonias duplicadas') END AS sin_duplicados \gset
\echo 'Insertados · entidades:' :insertadas_entidades '· municipios:' :insertados_municipios '· colonias:' :insertadas_colonias
SQL
            ."\n".$this->marcas()
            .<<<'SQL'
COMMIT;
SELECT round(extract(epoch FROM clock_timestamp() - :'sepomex_inicio'::timestamptz)::numeric, 1) AS segundos \gset
\echo '######## Transacción confirmada en' :segundos 's'

\echo '######## 5/5 · Verificación final (solo lectura)'
\ir 04_verificacion_catalogos.sql

SQL;
    }
}
