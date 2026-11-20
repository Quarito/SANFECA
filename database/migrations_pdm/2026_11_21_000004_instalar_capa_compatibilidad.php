<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F4/F5 · Capa de compatibilidad anfeca_compat: vistas con la forma de las tablas de public
 * sobre los datos del PDM, disparadores de escritura y guardia de public.
 *
 * No cambia el comportamiento mientras el modo efectivo sea apagado o espejo: las vistas solo se
 * usan cuando el middleware AplicarModoPdm fija search_path = anfeca_compat, public (principal /
 * exclusivo), y la guardia de public solo actúa en esos modos.
 *
 * Mismas protecciones que F1/F4: ruta separada, solo PostgreSQL, autorización explícita en la
 * base en uso. down() se niega en principal/exclusivo y nunca toca datos (solo objetos técnicos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql'
            || !DB::selectOne("select to_regclass('anfeca_modelo.seleccion_certificacion') as t")->t) {
            throw new RuntimeException('Requiere PostgreSQL con las extensiones F1 y F4 del PDM.');
        }
        if (DB::selectOne("select to_regnamespace('anfeca_compat') as n")->n) {
            throw new RuntimeException('La capa de compatibilidad (anfeca_compat) ya está instalada.');
        }

        $bd = DB::connection()->getDatabaseName();
        if ($bd === config('anfeca_modelo.bd_permitida') && env('ANFECA_PDM_AUTORIZAR_EXTENSIONES') !== 'si') {
            throw new RuntimeException("La base {$bd} es la de pruebas en uso. Defina ANFECA_PDM_AUTORIZAR_EXTENSIONES=si solo en ese comando, tras un respaldo verificado.");
        }

        DB::unprepared((string) file_get_contents(database_path('sql/anfeca_modelo/anfeca_compat_vistas.sql')));
        DB::unprepared((string) file_get_contents(database_path('sql/anfeca_modelo/anfeca_compat_escritura.sql')));

        // Correspondencias desde config/anfeca_pdm.php (la misma fuente que F2 y F3).
        $cat = (array) config('anfeca_pdm.catalogos');
        foreach ((array) $cat['documentos'] as $campo => $descripcion) {
            DB::table('anfeca_compat.mapa_documento')->insert(compact('campo', 'descripcion'));
        }
        $columnas = ['TEL_PARTICULAR' => 'telefono_particular', 'TEL_CELULAR' => 'telefono_celular', 'CORREO' => 'correo_electronico'];
        foreach ((array) $cat['tipos_comunicacion'] as $clave => $descripcion) {
            DB::table('anfeca_compat.mapa_comunicacion')->insert(['campo' => $columnas[$clave], 'descripcion' => $descripcion]);
        }
        foreach ((array) $cat['estados_solicitud'] as $siglas => $descripcion) {
            DB::table('anfeca_compat.mapa_estado_solicitud')->insert(compact('siglas', 'descripcion'));
        }
        foreach ((array) $cat['alias_entidades'] as $clave => $entidad) {
            DB::table('anfeca_compat.alias_entidad')->insert(['clave' => $clave, 'entidad' => $entidad, 'motivo' => null]);
        }
        foreach ((array) $cat['no_entidades'] as $clave => $motivo) {
            DB::table('anfeca_compat.alias_entidad')->insert(['clave' => $clave, 'entidad' => null, 'motivo' => $motivo]);
        }
        DB::table('anfeca_compat.parametro')->insert(['clave' => 'zona_app', 'valor' => (string) config('app.timezone', 'UTC')]);
    }

    public function down(): void
    {
        $modo = DB::table('anfeca_modelo.integracion_control')->value('ic_modo');
        if (in_array($modo, ['principal', 'exclusivo'], true)) {
            throw new RuntimeException("El modo registrado es '{$modo}': vuelva primero a espejo o apagado (en exclusivo, exporte antes con anfeca:pdm-exportar-public).");
        }

        // CASCADE solo alcanza objetos de anfeca_compat y los disparadores técnicos que dependen
        // de sus funciones (guardia de public y sincronización de users). No borra datos.
        DB::statement('drop schema anfeca_compat cascade');
    }
};
