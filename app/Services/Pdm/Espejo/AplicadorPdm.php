<?php

namespace App\Services\Pdm\Espejo;

use App\Services\Pdm\Migracion\PlanMigracionPdm;
use Illuminate\Support\Facades\DB;

/**
 * F3 · Aplica en anfeca_modelo un PlanMigracionPdm ACOTADO (una persona y/o una solicitud):
 * inserta lo nuevo, actualiza lo que cambió y elimina lo que ya no corresponde,
 * siempre por clave natural y solo dentro del alcance.
 *
 * Historial: un documento que deja de ser vigente NO se borra; su estado pasa a
 * «Sustituido». Así el PDM conserva todas las versiones, incluso las que public no registra.
 *
 * No abre transacción: corre dentro de la transacción de la operación de Laravel.
 */
class AplicadorPdm
{
    private const E = 'anfeca_modelo.';

    public function aplicar(PlanMigracionPdm $plan, array $alcance): void
    {
        $f = $plan->filas;
        $personas = array_column($f['persona'] ?? [], 'pers_id_persona');

        // --- Persona, usuario, domicilio y contacto -------------------------------------
        $this->sincronizar('persona', $f['persona'] ?? [], ['pers_id_persona'], 'pers_id_persona', $personas, false);

        $usuarios = array_map(function (array $u) {
            $u['usua_id_rol'] = $this->rol($u['_rol_nombre']);
            unset($u['_rol_nombre']);

            return $u;
        }, $f['usuario'] ?? []);
        $this->sincronizar('usuario', $usuarios, ['usua_id_usuario'], 'usua_id_usuario', array_column($usuarios, 'usua_id_usuario'), false);

        $this->sincronizar('domicilio', $f['domicilio'] ?? [], ['domi_id_persona'], 'domi_id_persona', $personas, true);
        $this->sincronizar('comunicacion', $f['comunicacion'] ?? [], ['cumu_id_persona', 'comu_id_tipo_comunicacion'], 'cumu_id_persona', $personas, true);

        // --- Solicitud y su expediente ---------------------------------------------------
        $solicitudes = $alcance['solicitudes'] ?? [];
        if ($solicitudes === []) {
            $this->alinearSecuencias();

            return;
        }

        $this->sincronizar('solicitud', $f['solicitud'] ?? [], ['soli_id_solicitud'], 'soli_id_solicitud', $solicitudes, false);
        $this->sincronizar('estado_solicitud', $f['estado_solicitud'] ?? [], ['esso_id_solicitud', 'esso_siglas'], 'esso_id_solicitud', $solicitudes, true);

        $formacion = array_map(function (array $p) {
            $p['pees_id_estuido_profesional'] = $this->estudio($p['espr_id_nivel_profesional'], $p['espr_nombre'], $p['espr_estado']);
            unset($p['espr_id_nivel_profesional'], $p['espr_nombre'], $p['espr_estado']);

            return $p;
        }, $f['persona_estudio_profesional'] ?? []);
        $this->sincronizar('persona_estudio_profesional', $formacion, ['pees_id_solicitud', 'pees_tipo'], 'pees_id_solicitud', $solicitudes, true);

        $this->documentos($f['solicitud_documento'] ?? [], $solicitudes);
        $this->pagos($f['pago'] ?? [], $solicitudes);

        $this->alinearSecuencias();
    }

    /**
     * Upsert por clave natural dentro de un alcance; opcionalmente elimina lo que sobra.
     */
    private function sincronizar(string $tabla, array $filas, array $clave, string $columnaAlcance, array $ids, bool $borrarSobrantes): void
    {
        $existentes = [];
        if ($ids !== []) {
            foreach (DB::table(self::E.$tabla)->whereIn($columnaAlcance, $ids)->get() as $fila) {
                $existentes[$this->clave((array) $fila, $clave)] = (array) $fila;
            }
        }

        $vistas = [];
        foreach ($filas as $fila) {
            $k = $this->clave($fila, $clave);
            $vistas[$k] = true;

            if (!isset($existentes[$k])) {
                DB::table(self::E.$tabla)->insert($fila);
                continue;
            }

            $cambios = [];
            foreach ($fila as $col => $valor) {
                if ($this->texto($valor) !== $this->texto($existentes[$k][$col] ?? null)) {
                    $cambios[$col] = $valor;
                }
            }
            if ($cambios !== []) {
                DB::table(self::E.$tabla)->where(array_intersect_key($fila, array_flip($clave)))->update($cambios);
            }
        }

        if ($borrarSobrantes) {
            foreach ($existentes as $k => $fila) {
                if (!isset($vistas[$k])) {
                    DB::table(self::E.$tabla)->where(array_intersect_key($fila, array_flip($clave)))->delete();
                }
            }
        }
    }

    /** Documentos vigentes (Cargado) y versiones anteriores (Sustituido), sin borrar historial. */
    private function documentos(array $filas, array $solicitudes): void
    {
        $cargado = (int) DB::table(self::E.'estado_documento')->where('esdo_estado_documento', 'Cargado')->value('esdo_id_estado_documento');
        $sustituido = (int) DB::table(self::E.'estado_documento')->where('esdo_estado_documento', 'Sustituido')->value('esdo_id_estado_documento');
        $ahora = now()->setTimezone(PlanMigracionPdm::ZONA_HORARIA_PDM);

        $actuales = collect(DB::table(self::E.'solicitud_documento as d')
            ->join(self::E.'estado_solicitud_documento as e', 'e.esdo_id_documentacion', '=', 'd.socu_id_documentacion')
            ->whereIn('d.socu_id_solicitud', $solicitudes)
            ->get(['d.socu_id_documentacion', 'd.socu_id_solicitud', 'd.docu_id_documento', 'd.socu_url_archivo', 'e.esdo_id_estado_solicitud_docume', 'e.esdo_id_estado_documento']));

        $marcar = function (object $doc, int $estado, string $observacion) use ($ahora) {
            DB::table(self::E.'estado_solicitud_documento')
                ->where('esdo_id_estado_solicitud_docume', $doc->esdo_id_estado_solicitud_docume)
                ->update(['esdo_id_estado_documento' => $estado, 'esdo_observacion' => $observacion,
                    'esdo_fecha' => $ahora->toDateString(), 'esdo_hora' => $ahora->format('H:i:s')]);
            $doc->esdo_id_estado_documento = $estado;
        };

        $insertar = function (array $fila) {
            $estado = ['esdo_id_estado_documento' => $fila['esdo_id_estado_documento'], 'esdo_observacion' => $fila['esdo_observacion']];
            unset($fila['esdo_id_estado_documento'], $fila['esdo_observacion']);
            $id = DB::table(self::E.'solicitud_documento')->insertGetId($fila, 'socu_id_documentacion');
            DB::table(self::E.'estado_solicitud_documento')->insert($estado + [
                'esdo_id_documentacion' => $id, 'esdo_fecha' => $fila['socu_fecha'], 'esdo_hora' => $fila['socu_hora'],
            ]);
        };

        $vigentesPlan = [];
        foreach ($filas as $fila) {
            $mismo = $actuales->first(fn ($d) => $d->socu_id_solicitud == $fila['socu_id_solicitud']
                && $d->docu_id_documento == $fila['docu_id_documento'] && $d->socu_url_archivo === $fila['socu_url_archivo']);

            if ((int) $fila['esdo_id_estado_documento'] === $cargado) {
                $vigentesPlan[] = $fila;

                if ($mismo === null) {
                    // Nueva versión: la vigente anterior del mismo documento pasa a Sustituido.
                    foreach ($actuales as $d) {
                        if ($d->socu_id_solicitud == $fila['socu_id_solicitud'] && $d->docu_id_documento == $fila['docu_id_documento']
                            && (int) $d->esdo_id_estado_documento === $cargado) {
                            $marcar($d, $sustituido, 'Versión anterior conservada (historial)');
                        }
                    }
                    $insertar($fila);
                } elseif ((int) $mismo->esdo_id_estado_documento !== $cargado) {
                    $marcar($mismo, $cargado, 'Archivo vigente');
                }
                continue;
            }

            // Versión anterior registrada en public (historial): debe existir como Sustituido.
            if ($mismo === null) {
                $insertar($fila);
            } elseif ((int) $mismo->esdo_id_estado_documento !== $sustituido) {
                $marcar($mismo, $sustituido, 'Versión anterior conservada (historial)');
            }
        }

        // Vigentes en el PDM que public ya no tiene (documento retirado): se conservan como Sustituido.
        foreach ($actuales as $d) {
            if ((int) $d->esdo_id_estado_documento !== $cargado) {
                continue;
            }
            $sigue = collect($vigentesPlan)->contains(fn ($f) => $f['socu_id_solicitud'] == $d->socu_id_solicitud
                && $f['docu_id_documento'] == $d->docu_id_documento && $f['socu_url_archivo'] === $d->socu_url_archivo);
            if (!$sigue) {
                $marcar($d, $sustituido, 'Versión anterior conservada (historial)');
            }
        }
    }

    /** PAGO: una fila vigente por solicitud (el historial del archivo vive en SOLICITUD_DOCUMENTO). */
    private function pagos(array $filas, array $solicitudes): void
    {
        $porSolicitud = collect($filas)->keyBy('pago_id_solicitud');

        foreach ($solicitudes as $s) {
            $existentes = DB::table(self::E.'pago')->where('pago_id_solicitud', $s)->orderBy('pago_id_pago')->get();
            $plan = $porSolicitud[$s] ?? null;

            if ($plan === null) {
                DB::table(self::E.'pago')->where('pago_id_solicitud', $s)->delete();
                continue;
            }
            if ($existentes->isEmpty()) {
                DB::table(self::E.'pago')->insert($plan);
                continue;
            }
            DB::table(self::E.'pago')->where('pago_id_pago', $existentes->first()->pago_id_pago)->update(['pago_url_comprobante' => $plan['pago_url_comprobante']]);
            DB::table(self::E.'pago')->where('pago_id_solicitud', $s)->where('pago_id_pago', '<>', $existentes->first()->pago_id_pago)->delete();
        }
    }

    private function rol(string $nombre): int
    {
        return (int) (DB::table(self::E.'rol')->where('rol_nombre', $nombre)->value('rol_id_rol')
            ?? DB::table(self::E.'rol')->insertGetId(['rol_nombre' => $nombre], 'rol_id_rol'));
    }

    private function estudio(int $nivel, string $nombre, string $estado): int
    {
        return (int) (DB::table(self::E.'estudio_profesional')->where('espr_id_nivel_profesional', $nivel)->where('espr_nombre', $nombre)->value('espr_id_estudio_profesional')
            ?? DB::table(self::E.'estudio_profesional')->insertGetId(
                ['espr_id_nivel_profesional' => $nivel, 'espr_nombre' => $nombre, 'espr_estado' => $estado],
                'espr_id_estudio_profesional'
            ));
    }

    /** Tablas con ID conservado: la secuencia nunca queda por debajo del máximo. */
    private function alinearSecuencias(): void
    {
        foreach (['persona' => 'pers_id_persona', 'usuario' => 'usua_id_usuario', 'solicitud' => 'soli_id_solicitud', 'rol' => 'rol_id_rol'] as $tabla => $pk) {
            DB::statement("select setval(pg_get_serial_sequence('anfeca_modelo.{$tabla}', '{$pk}'),
                greatest((select coalesce(max({$pk}), 1) from anfeca_modelo.{$tabla}),
                         (select last_value from ".DB::selectOne("select pg_get_serial_sequence('anfeca_modelo.{$tabla}', '{$pk}') as s")->s.')))');
        }
    }

    private function clave(array $fila, array $columnas): string
    {
        return implode('|', array_map(fn ($c) => (string) $this->texto($fila[$c] ?? null), $columnas));
    }

    private function texto(mixed $v): ?string
    {
        return match (true) {
            $v === null => null,
            is_bool($v) => $v ? '1' : '0',
            default => (string) $v,
        };
    }
}
