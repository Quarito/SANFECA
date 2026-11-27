<?php

namespace App\Console\Commands;

use App\Services\Pdm\Compat\EquivalenciaPdm;
use App\Services\Pdm\Migracion\ConciliadorPdm;
use App\Services\Pdm\Migracion\PlanMigracionPdm;
use App\Services\Pdm\BasePdm;
use App\Services\Pdm\ModoPdm;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * F5 · REVERSIÓN desde el modo exclusivo: lleva a public lo capturado solo en el PDM, para poder
 * volver a principal, espejo o apagado sin perder información.
 *
 * - Solo en modo exclusivo; en la base en uso exige ANFECA_PDM_AUTORIZAR_EXPORTACION=si.
 * - Solo escribe lo capturado en exclusivo: filas nuevas y, en filas existentes, únicamente las
 *   columnas que difieren DESPUÉS de las normalizaciones documentadas (EquivalenciaPdm). Un dato
 *   que nadie modificó (p. ej., «cdmx» frente a «Ciudad de México») no se reescribe.
 * - Nunca elimina filas de public; el historial solo se agrega.
 * - Todo en una transacción con las tablas bloqueadas; al final exige conciliación y equivalencia
 *   en 0. Si no, revierte y public queda como estaba.
 * - Los catálogos (certificados_historicos, instituciones_afiliadas) no se capturan en ANFECA: no se tocan.
 */
class AnfecaPdmExportarPublic extends Command
{
    protected $signature = 'anfeca:pdm-exportar-public {--confirmar : Omite la confirmación interactiva}';

    protected $description = 'Copia a public los datos capturados en modo exclusivo (reversión de F5)';

    private const TABLAS = [
        'datos_solicitantes', 'selecciones_certificacion', 'solicitudes_certificacion', 'datos_refrendo',
        'formaciones_academicas_solicitud', 'documentos_probatorios_solicitud',
    ];

    public function handle(): int
    {
        try {
            if ((ModoPdm::activacion()?->ic_modo) !== ModoPdm::EXCLUSIVO) {
                throw new RuntimeException('La exportación solo aplica en modo exclusivo (en principal public ya es réplica).');
            }

            $bd = DB::connection()->getDatabaseName();
            BasePdm::verificarEscritura('ANFECA_PDM_AUTORIZAR_EXPORTACION');

            if (!$this->option('confirmar') && !$this->confirm("¿Copiar a public de {$bd} los datos del PDM?", false)) {
                $this->warn('Cancelado.');

                return self::FAILURE;
            }

            $resumen = DB::transaction(fn () => $this->exportar());
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Tabla', 'Filas insertadas o actualizadas'], array_map(fn ($t, $n) => [$t, $n], array_keys($resumen), $resumen));
        $this->info('Exportación verificada (conciliación y equivalencia en 0). Ya puede ejecutar anfeca:pdm-modo principal o apagado.');

        return self::SUCCESS;
    }

    /** @return array<string,int> */
    private function exportar(): array
    {
        $tablas = array_merge(self::TABLAS, ['historial_documentos_academicos']);
        DB::statement('lock table '.implode(', ', array_map(fn ($t) => "public.{$t}", $tablas)).' in share row exclusive mode');
        DB::statement("select set_config('anfeca.exportando', 'si', true)"); // solo esta transacción

        // Qué cambió realmente en el PDM respecto a public (con las normalizaciones documentadas).
        $pendientes = [];
        foreach ((new EquivalenciaPdm())->verificar() as $d) {
            if (!in_array($d['tabla'], self::TABLAS, true)) {
                continue;
            }
            if ($d['tipo'] === 'SOBRANTE') {
                $pendientes[$d['tabla']]['nuevas'][] = (int) $d['clave'];
            } elseif ($d['tipo'] === 'DISTINTA') {
                $pendientes[$d['tabla']]['cambios'][(int) $d['clave']][] = $d['columna'];
            } else {
                // FALTANTE/DUPLICADA: public tiene algo que el PDM no explica. No se adivina: se detiene.
                throw new RuntimeException("Exportación detenida: {$d['tabla']} {$d['tipo']} ({$d['clave']}): {$d['detalle']}");
            }
        }

        $resumen = [];
        foreach (self::TABLAS as $tabla) {
            $columnas = array_map(fn ($c) => $c->column_name, DB::select(
                "select v.column_name from information_schema.columns v
                join information_schema.columns p on p.table_schema = 'public' and p.table_name = v.table_name and p.column_name = v.column_name
                where v.table_schema = 'anfeca_compat' and v.table_name = ? order by v.ordinal_position", [$tabla]));
            $lista = implode(', ', array_map(fn ($c) => '"'.$c.'"', $columnas));
            $n = 0;

            $nuevas = $pendientes[$tabla]['nuevas'] ?? [];
            if ($nuevas !== []) {
                $n += DB::affectingStatement("insert into public.{$tabla} ({$lista}) select {$lista} from anfeca_compat.{$tabla} where id in ("
                    .implode(', ', array_fill(0, count($nuevas), '?')).')', $nuevas);
                DB::statement("select setval(pg_get_serial_sequence('public.{$tabla}', 'id'), greatest((select coalesce(max(id), 0) from public.{$tabla}), 1))");
            }

            foreach ($pendientes[$tabla]['cambios'] ?? [] as $id => $cols) {
                $sinDestino = array_diff($cols, $columnas);
                if ($sinDestino !== []) {
                    throw new RuntimeException("Exportación detenida en {$tabla}: faltan columnas compatibles para ".implode(', ', $sinDestino).'.');
                }
                $cols = array_unique($cols);
                $asignar = implode(', ', array_map(fn ($c) => "\"{$c}\" = v.\"{$c}\"", $cols));
                $n += DB::affectingStatement("update public.{$tabla} p set {$asignar} from anfeca_compat.{$tabla} v where v.id = p.id and p.id = ?", [$id]);
            }

            $resumen[$tabla] = $n;
        }

        $resumen['historial_documentos_academicos'] = DB::affectingStatement('insert into public.historial_documentos_academicos
                (solicitud_id, tipo_documento, ruta_archivo, created_at, updated_at)
            select v.solicitud_id, v.tipo_documento, v.ruta_archivo, v.created_at, v.updated_at
            from anfeca_compat.historial_documentos_academicos v
            where not exists (select 1 from public.historial_documentos_academicos p
                where p.solicitud_id = v.solicitud_id and p.tipo_documento = v.tipo_documento and p.ruta_archivo = v.ruta_archivo)');

        $conciliacion = (new ConciliadorPdm())->conciliar(PlanMigracionPdm::construir());
        $equivalencia = (new EquivalenciaPdm())->verificar();
        if ($conciliacion !== [] || $equivalencia !== []) {
            throw new RuntimeException('Exportación REVERTIDA: tras copiar quedan '.count($conciliacion).' diferencia(s) de conciliación y '
                .count($equivalencia).' de equivalencia. public no se modificó (revise anfeca:pdm-conciliar / anfeca:pdm-equivalencia).');
        }

        return $resumen;
    }
}
