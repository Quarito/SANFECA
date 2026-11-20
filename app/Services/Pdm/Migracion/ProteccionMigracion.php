<?php

namespace App\Services\Pdm\Migracion;

use App\Services\Pdm\ModoPdm;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Precondiciones de cada operación de F2.
 *
 *  planear / conciliar → solo lectura: cualquier base con el PDM, extensiones y catálogos.
 *  simular             → escribe y revierte: SOLO en una base aislada *_pruebas_pg
 *                        (las secuencias avanzan aunque se revierta la transacción).
 *  ejecutar / vaciar   → base aislada, o la base en uso con autorización explícita en el entorno:
 *                        ANFECA_PDM_AUTORIZAR_MIGRACION=si / ANFECA_PDM_AUTORIZAR_VACIADO=si.
 */
class ProteccionMigracion
{
    public static function verificar(string $operacion): void
    {
        $con = DB::connection();

        if ($con->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Requiere PostgreSQL.');
        }
        if (!DB::selectOne("select to_regclass('anfeca_modelo.zona') as t")->t) {
            throw new RuntimeException('Faltan el esquema anfeca_modelo o sus extensiones (F1).');
        }
        if (DB::table('anfeca_modelo.entidad_federativa')->count() !== 32) {
            throw new RuntimeException('Faltan los catálogos del PDM (CatalogosPdmSeeder).');
        }

        if (in_array($operacion, ['planear', 'conciliar'], true)) {
            return;
        }

        ModoPdm::apagado(); // lanza excepción si el modo no es válido en esta fase

        $bd = (string) $con->getDatabaseName();
        $aislada = str_ends_with($bd, (string) config('anfeca_pdm.sufijo_bd_pruebas')) && $bd !== config('anfeca_modelo.bd_permitida');

        if ($operacion === 'simular' && !$aislada) {
            throw new RuntimeException("La simulación solo se permite en una base aislada *_pruebas_pg (actual: {$bd}). En la base en uso, use el plan (sin opciones).");
        }

        if (in_array($operacion, ['ejecutar', 'vaciar'], true) && !$aislada) {
            $variable = $operacion === 'ejecutar' ? 'ANFECA_PDM_AUTORIZAR_MIGRACION' : 'ANFECA_PDM_AUTORIZAR_VACIADO';

            if ($bd !== config('anfeca_modelo.bd_permitida')) {
                throw new RuntimeException("Base '{$bd}' no permitida para {$operacion}.");
            }
            if (env($variable) !== 'si') {
                throw new RuntimeException("Para {$operacion} en {$bd} defina {$variable}=si solo en ese comando, después de un respaldo verificado.");
            }
        }

        if (in_array($operacion, ['ejecutar', 'simular'], true)) {
            foreach (ConciliadorPdm::TABLAS_DOMINIO as $tabla) {
                if (DB::table("anfeca_modelo.{$tabla}")->exists()) {
                    throw new RuntimeException("anfeca_modelo.{$tabla} ya tiene datos: la migración solo parte de un PDM vacío (use anfeca:pdm-conciliar o anfeca:pdm-vaciar).");
                }
            }
        }
    }

    /**
     * Huella de las tablas de dominio de public (para demostrar que no cambian).
     *
     * @return array<string,string>
     */
    public static function huellaPublic(): array
    {
        $tablas = ['users', 'datos_solicitantes', 'solicitudes_certificacion', 'datos_refrendo', 'selecciones_certificacion',
            'formaciones_academicas_solicitud', 'documentos_probatorios_solicitud', 'historial_documentos_academicos',
            'certificados_historicos', 'instituciones_afiliadas', 'zonas', 'coordinador_zona', 'roles', 'privilegios', 'rol_privilegio'];

        $huella = [];
        foreach ($tablas as $t) {
            $fila = DB::selectOne("select count(*) as n, coalesce(md5(string_agg(x::text, '|' order by x.id)), '') as h from public.{$t} x");
            $huella[$t] = "{$fila->n}:{$fila->h}";
        }

        return $huella;
    }
}
