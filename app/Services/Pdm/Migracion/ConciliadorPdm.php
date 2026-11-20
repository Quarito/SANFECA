<?php

namespace App\Services\Pdm\Migracion;

use Illuminate\Support\Facades\DB;

/**
 * SOLO LECTURA. Compara lo que hay en anfeca_modelo con lo que el plan (calculado
 * de nuevo desde public) dice que debe haber: filas faltantes, sobrantes, duplicadas
 * y valores distintos, columna por columna.
 */
class ConciliadorPdm
{
    /** Versiones anteriores que solo conserva el PDM (informativo, no es diferencia). */
    public int $historialAdicional = 0;

    /** Clave natural de cada tabla del plan. */
    public const CLAVES = [
        'rol' => ['rol_nombre'],
        'privilegio' => ['priv_id_privilegio'],
        'rol_privilegio' => ['ropr_id_rol_privilegio'],
        'zona' => ['zona_id_zona'],
        'institucion' => ['inst_id_institucion'],
        'sede' => ['inst_id_institucion', 'sede_descripcion'],
        'persona' => ['pers_id_persona'],
        'usuario' => ['usua_id_usuario'],
        'domicilio' => ['domi_id_persona'],
        'comunicacion' => ['cumu_id_persona', 'comu_id_tipo_comunicacion'],
        'coordinador_zona' => ['cozo_id_zona', 'cozo_id_usuario'],
        'historico_certificado_importacion' => ['hcim_id_importacion'],
        'historico_certificado' => ['hice_id_importacion', 'hice_tipo'],
        'solicitud' => ['soli_id_solicitud'],
        'estado_solicitud' => ['esso_id_solicitud', 'esso_siglas'],
        'persona_estudio_profesional' => ['pees_id_solicitud', 'pees_tipo'],
        'solicitud_documento' => ['socu_id_solicitud', 'docu_id_documento', 'socu_url_archivo', 'esdo_id_estado_documento'],
        'pago' => ['pago_id_solicitud', 'pago_url_comprobante'],
    ];

    /** Columnas del plan que no se comparan directamente. */
    private const OMITIR = [
        'rol' => ['rol_id_rol'],      // los roles creados desde users.rol no traen ID
        'usuario' => ['usua_id_rol'], // se compara por nombre de rol (_rol_nombre)
        // Fechas de carga: public no guarda una fecha por archivo; se comparan existencia, ruta y estado.
        'solicitud_documento' => ['socu_fecha', 'socu_hora'],
    ];

    /** Lecturas que reconstruyen la forma del plan a partir de varias tablas. */
    private const LECTURAS = [
        'usuario' => 'select u.*, r.rol_nombre as _rol_nombre from anfeca_modelo.usuario u join anfeca_modelo.rol r on r.rol_id_rol = u.usua_id_rol',
        'persona_estudio_profesional' => 'select p.*, e.espr_id_nivel_profesional, e.espr_nombre, e.espr_estado
            from anfeca_modelo.persona_estudio_profesional p
            join anfeca_modelo.estudio_profesional e on e.espr_id_estudio_profesional = p.pees_id_estuido_profesional',
        'solicitud_documento' => 'select d.*, s.esdo_id_estado_documento, s.esdo_observacion
            from anfeca_modelo.solicitud_documento d
            join anfeca_modelo.estado_solicitud_documento s on s.esdo_id_documentacion = d.socu_id_documentacion',
    ];

    /** Tablas de dominio que no deben tener filas fuera del plan. */
    public const TABLAS_DOMINIO = [
        'rol', 'privilegio', 'rol_privilegio', 'zona', 'institucion', 'sede', 'persona', 'usuario', 'domicilio',
        'comunicacion', 'coordinador_zona', 'historico_certificado_importacion', 'historico_certificado', 'solicitud',
        'estado_solicitud', 'persona_estudio_profesional', 'solicitud_documento', 'estado_solicitud_documento', 'pago',
    ];

    /**
     * @return list<array{tabla:string, tipo:string, clave:string, detalle:string}> diferencias (vacío = conciliado)
     */
    public function conciliar(PlanMigracionPdm $plan): array
    {
        $diferencias = [];

        $sustituido = DB::table('anfeca_modelo.estado_documento')->where('esdo_estado_documento', 'Sustituido')->value('esdo_id_estado_documento');
        $historialAdicional = 0;

        foreach (self::CLAVES as $tabla => $clave) {
            $esperadas = $plan->filas[$tabla] ?? [];
            $reales = DB::select(self::LECTURAS[$tabla] ?? "select * from anfeca_modelo.{$tabla}");

            $indice = [];
            foreach ($reales as $fila) {
                $fila = (array) $fila;
                $k = $this->clave($fila, $clave);
                if (isset($indice[$k])) {
                    $diferencias[] = $this->dif($tabla, 'DUPLICADA', $k, 'La clave aparece más de una vez en el PDM.');
                }
                $indice[$k] = $fila;
            }

            $vistas = [];
            foreach ($esperadas as $esperada) {
                $k = $this->clave($esperada, $clave);
                $vistas[$k] = true;

                if (!isset($indice[$k])) {
                    $diferencias[] = $this->dif($tabla, 'FALTANTE', $k, 'Fila del plan ausente en el PDM.');
                    continue;
                }

                foreach ($esperada as $col => $valor) {
                    if (in_array($col, self::OMITIR[$tabla] ?? [], true)) {
                        continue;
                    }
                    // Versiones anteriores: basta con que existan (clave con ruta y estado).
                    if ($tabla === 'solicitud_documento' && $col === 'esdo_observacion' && (string) $esperada['esdo_id_estado_documento'] === (string) $sustituido) {
                        continue;
                    }
                    $real = $indice[$k][$col] ?? null;
                    if ($this->valor($valor) !== $this->valor($real)) {
                        $diferencias[] = $this->dif($tabla, 'DISTINTA', $k, "{$col}: esperado ".$this->mostrar($valor).', encontrado '.$this->mostrar($real).'.');
                    }
                }
            }

            foreach (array_keys($indice) as $k) {
                // El PDM conserva versiones anteriores que public no registra (F3): no es diferencia.
                if (!isset($vistas[$k]) && $tabla === 'solicitud_documento' && (string) $indice[$k]['esdo_id_estado_documento'] === (string) $sustituido) {
                    $historialAdicional++;
                    continue;
                }
                if (!isset($vistas[$k])) {
                    $diferencias[] = $this->dif($tabla, 'SOBRANTE', $k, 'Fila en el PDM que no corresponde a public.');
                }
            }
        }

        $this->historialAdicional = $historialAdicional;

        // Cada documento tiene exactamente un estado.
        $sinEstado = DB::selectOne('select count(*) as n from anfeca_modelo.solicitud_documento d
            where (select count(*) from anfeca_modelo.estado_solicitud_documento e where e.esdo_id_documentacion = d.socu_id_documentacion) <> 1')->n;
        if ((int) $sinEstado > 0) {
            $diferencias[] = $this->dif('estado_solicitud_documento', 'DISTINTA', '*', "{$sinEstado} documento(s) sin exactamente un estado.");
        }

        return $diferencias;
    }

    private function clave(array $fila, array $columnas): string
    {
        return implode('|', array_map(fn ($c) => (string) $this->valor($fila[$c] ?? null), $columnas));
    }

    private function valor(mixed $v): ?string
    {
        return match (true) {
            $v === null => null,
            is_bool($v) => $v ? '1' : '0',
            default => (string) $v,
        };
    }

    /** Muestra valores sin exponer datos sensibles largos (p. ej., hashes). */
    private function mostrar(mixed $v): string
    {
        $t = $this->valor($v);

        return $t === null ? 'nulo' : (mb_strlen($t) > 40 ? '«'.mb_substr($t, 0, 12).'…» ('.mb_strlen($t).' car.)' : "«{$t}»");
    }

    private function dif(string $tabla, string $tipo, string $clave, string $detalle): array
    {
        return compact('tabla', 'tipo', 'clave', 'detalle');
    }
}
