<?php

namespace App\Services\Pdm\Migracion;

use Illuminate\Support\Facades\DB;

/**
 * Escribe en anfeca_modelo las filas de un PlanMigracionPdm.
 * No abre transacción propia: el llamador (comando o prueba) la controla,
 * para que escritura y conciliación se confirmen o reviertan juntas.
 */
class MigradorPdm
{
    private const E = 'anfeca_modelo.';

    /** Tablas con ID conservado de public: su secuencia se alinea al terminar. */
    private const SECUENCIAS = [
        'rol' => 'rol_id_rol', 'privilegio' => 'priv_id_privilegio', 'rol_privilegio' => 'ropr_id_rol_privilegio',
        'zona' => 'zona_id_zona', 'institucion' => 'inst_id_institucion', 'persona' => 'pers_id_persona',
        'usuario' => 'usua_id_usuario', 'solicitud' => 'soli_id_solicitud',
        'seleccion_certificacion' => 'secc_id_seleccion', 'solicitud_refrendo' => 'sore_id_solicitud_refrendo',
        'formacion_solicitud' => 'fosc_id_formacion', 'documentos_solicitud' => 'dosc_id_documentos',
    ];

    /** @return array<string,int> filas escritas por tabla */
    public function escribir(PlanMigracionPdm $plan): array
    {
        $escritas = [];
        $f = $plan->filas;

        $roles = [];
        $rolesPlan = $f['rol'] ?? [];
        foreach ($rolesPlan as $rol) {
            if ($rol['rol_id_rol'] !== null) {
                DB::table(self::E.'rol')->insert($rol);
                $roles[$rol['rol_nombre']] = $rol['rol_id_rol'];
            }
        }
        // Antes de pedir IDs automáticos, avanzar sobre los IDs conservados de public.roles.
        $this->alinearSecuencia('rol', self::SECUENCIAS['rol']);
        foreach ($rolesPlan as $rol) {
            if ($rol['rol_id_rol'] === null) {
                $roles[$rol['rol_nombre']] = DB::table(self::E.'rol')->insertGetId(
                    ['rol_nombre' => $rol['rol_nombre']], 'rol_id_rol'
                );
            }
        }
        $escritas['rol'] = count($rolesPlan);

        foreach (['privilegio', 'rol_privilegio', 'zona', 'institucion', 'sede', 'persona'] as $tabla) {
            $escritas[$tabla] = $this->insertar($tabla, $f[$tabla] ?? []);
        }

        $usuarios = array_map(function (array $u) use ($roles) {
            $u['usua_id_rol'] = $roles[$u['_rol_nombre']];
            unset($u['_rol_nombre']);

            return $u;
        }, $f['usuario'] ?? []);
        $escritas['usuario'] = $this->insertar('usuario', $usuarios);

        foreach (['seleccion_certificacion', 'domicilio', 'comunicacion', 'coordinador_zona', 'historico_certificado_importacion', 'historico_certificado',
            'solicitud', 'estado_solicitud', 'solicitud_refrendo', 'formacion_solicitud', 'documentos_solicitud'] as $tabla) {
            $escritas[$tabla] = $this->insertar($tabla, $f[$tabla] ?? []);
        }

        // Formación: resolver el estudio (catálogo ACT o título en revisión REV).
        $escritas['persona_estudio_profesional'] = 0;
        foreach ($f['persona_estudio_profesional'] ?? [] as $p) {
            $estudio = DB::table(self::E.'estudio_profesional')
                ->where('espr_id_nivel_profesional', $p['espr_id_nivel_profesional'])
                ->where('espr_nombre', $p['espr_nombre'])->value('espr_id_estudio_profesional');

            $estudio ??= DB::table(self::E.'estudio_profesional')->insertGetId([
                'espr_id_nivel_profesional' => $p['espr_id_nivel_profesional'],
                'espr_nombre' => $p['espr_nombre'], 'espr_estado' => $p['espr_estado'],
            ], 'espr_id_estudio_profesional');

            unset($p['espr_id_nivel_profesional'], $p['espr_nombre'], $p['espr_estado']);
            $p['pees_id_estuido_profesional'] = $estudio;
            DB::table(self::E.'persona_estudio_profesional')->insert($p);
            $escritas['persona_estudio_profesional']++;
        }

        // Documentos: cada archivo con su estado (vigente o sustituido).
        $escritas['solicitud_documento'] = 0;
        foreach ($f['solicitud_documento'] ?? [] as $d) {
            $estado = ['esdo_id_estado_documento' => $d['esdo_id_estado_documento'], 'esdo_observacion' => $d['esdo_observacion']];
            unset($d['esdo_id_estado_documento'], $d['esdo_observacion']);

            $id = DB::table(self::E.'solicitud_documento')->insertGetId($d, 'socu_id_documentacion');
            DB::table(self::E.'estado_solicitud_documento')->insert($estado + [
                'esdo_id_documentacion' => $id, 'esdo_fecha' => $d['socu_fecha'], 'esdo_hora' => $d['socu_hora'],
            ]);
            $escritas['solicitud_documento']++;
        }

        $escritas['pago'] = $this->insertar('pago', $f['pago'] ?? []);

        foreach (self::SECUENCIAS as $tabla => $pk) {
            $this->alinearSecuencia($tabla, $pk);
        }

        return $escritas;
    }

    private function alinearSecuencia(string $tabla, string $pk): void
    {
        DB::statement("select setval(pg_get_serial_sequence('anfeca_modelo.{$tabla}', '{$pk}'),
                greatest(coalesce((select max({$pk}) from anfeca_modelo.{$tabla}), 0), 1),
                (select count(*) > 0 from anfeca_modelo.{$tabla}))");
    }

    private function insertar(string $tabla, array $filas): int
    {
        foreach (array_chunk($filas, 200) as $lote) {
            DB::table(self::E.$tabla)->insert($lote);
        }

        return count($filas);
    }
}
