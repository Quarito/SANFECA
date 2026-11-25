<?php

namespace App\Services\Coordinacion;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consulta ÚNICA de solicitudes para el módulo de coordinación.
 * La usan el dashboard, la bandeja, el expediente y las descargas, de modo que
 * indicadores y listados comparten exactamente el mismo alcance y las mismas reglas.
 *
 * Relaciones (todas 1:1 respecto de la solicitud, sin duplicar filas):
 *   s  solicitudes_certificacion
 *   u  users                          (excluye cuentas de coordinación)
 *   f  formaciones_academicas_solicitud (solicitud_id único)
 *   i  instituciones_afiliadas
 *   z  zonas
 *   d  datos_solicitantes             (user_id único)
 *   h  certificados_historicos
 */
class ConsultaSolicitudes
{
    public function __construct(private readonly AlcanceZona $alcance)
    {
    }

    public function base(User $usuario): Builder
    {
        $consulta = DB::table('solicitudes_certificacion as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('formaciones_academicas_solicitud as f', 'f.solicitud_id', '=', 's.id')
            ->leftJoin('instituciones_afiliadas as i', 'i.id', '=', 'f.institucion_afiliada_id')
            ->leftJoin('zonas as z', 'z.id', '=', 'i.zona_id')
            ->leftJoin('datos_solicitantes as d', 'd.user_id', '=', 's.user_id')
            ->leftJoin('certificados_historicos as h', 'h.id', '=', 's.certificado_historico_id')
            ->where(fn (Builder $q) => $q->whereNull('u.rol')->orWhereNotIn('u.rol', User::ROLES_COORDINACION));

        $zonas = $this->alcance->zonasAutorizadas($usuario);

        if ($zonas === null) {
            return $consulta;                       // nacional: todo, incluidas las «sin zona»
        }

        if ($zonas === []) {
            return $consulta->whereRaw('1 = 0');    // sin asignación: nada
        }

        return $consulta->whereIn('i.zona_id', $zonas); // excluye también las de zona nula
    }

    /** Columnas de la bandeja y del expediente (sin rutas de archivos). */
    public static function columnas(): array
    {
        return [
            's.id as folio',
            's.estado', 's.tipo_tramite', 's.certificacion', 's.tipo_personal', 's.area_certificacion',
            's.fecha_envio', 's.created_at', 's.compromiso_aceptado',
            'z.id as zona_id', 'z.siglas as zona_siglas', 'z.descripcion as zona_descripcion',
            'i.id as institucion_id', 'i.nombre as institucion', 'i.escuela_facultad as facultad',
            'd.nombres', 'd.apellido_paterno', 'd.apellido_materno',
            'h.numero_certificado as certificado_historico',
        ];
    }

    /** Solicitud dentro del alcance, o null (también si no existe). */
    public function detalle(User $usuario, int $id): ?object
    {
        return $this->base($usuario)
            ->where('s.id', $id)
            ->select(array_merge(self::columnas(), [
                's.user_id', 'd.curp', 'd.correo_electronico',
                'f.nivel_origen', 'f.titulo_origen', 'f.titulo_origen_otro',
                'f.nivel_ultimo_grado', 'f.titulo_ultimo_grado', 'f.titulo_ultimo_grado_otro',
                'f.formacion_completada_at', 'i.campus', 'i.zona_anfeca',
                's.compromiso_aceptado_en',
            ]))
            ->first();
    }

    public function permite(User $usuario, int $id): bool
    {
        return $this->base($usuario)->where('s.id', $id)->exists();
    }
}
