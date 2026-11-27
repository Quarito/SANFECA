<?php

namespace App\Services\Coordinacion;

use App\Models\Cuenta;
use App\Services\Anfeca\FormacionPdm;
use App\Services\Anfeca\Instituciones;
use App\Services\Anfeca\Tramites;
use App\Services\Zonas\ZonasAnfeca;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consulta ÚNICA de solicitudes para el módulo de coordinación, sobre las 36 tablas oficiales.
 * La usan el dashboard, la bandeja, el expediente y las descargas: comparten el mismo alcance.
 *
 * Proyecciones (subconsultas, sin vistas ni tablas en la base) con los nombres que ya usa el módulo:
 *   s  SOLICITUD + CONVOCATORIA/TIPO_CONVOCATORIA + AREA + TIPO_PERSONAL + eventos de ESTADO_SOLICITUD
 *   u  USUARIO + ROL (solo cuentas de solicitante)
 *   d  PERSONA + COMUNICACION (correo)
 *   i  INSTITUCION + SEDE (institución de afiliación de la persona)
 *   h  número del certificado histórico del refrendo (SOLI_NUMERO_CERTIFICADO)
 * Las fechas se entregan con zona horaria (las columnas DATE/TIME del modelo guardan la hora de la Ciudad de México).
 */
class ConsultaSolicitudes
{
    public function __construct(
        private readonly AlcanceZona $alcance,
        private readonly ZonasAnfeca $zonas,
        private readonly Instituciones $instituciones,
    ) {
    }

    public static function proyeccionSolicitudes(): Builder
    {
        $evento = fn (string $siglas) => "(select min(e.esso_fecha + e.esso_hora) at time zone '".Tramites::ZONA."' from estado_solicitud e "
            ."where e.esso_id_solicitud = so.soli_id_solicitud and e.esso_siglas = '{$siglas}')";

        return DB::table('solicitud as so')
            ->join('usuario as us', 'us.usua_id_persona', '=', 'so.soli_id_persona')
            ->leftJoin('convocatoria as c', 'c.conv_id_convocatoria', '=', 'so.soli_id_convocatoria')
            ->leftJoin('tipo_convocatoria as tc', 'tc.tico_id_tipo_convocatoria', '=', 'c.conv_id_tipo_convocatoria')
            ->join('area_certificacion as a', 'a.area_id_area_certificacion', '=', 'so.soli_id_area_certificacion')
            ->join('tipo_personal_academico as tp', 'tp.tipa_id_tipo_personal_academico', '=', 'so.soli_id_tipo_personal_academico')
            ->selectRaw("so.soli_id_solicitud as id, us.usua_id_usuario as user_id, so.soli_id_persona as persona_id,
                tc.tico_siglas as certificacion,
                case when so.soli_numero_certificado is null then 'inicial' else 'refrendo' end as tipo_tramite,
                case when {$evento('EN')} is null then 'borrador' else 'enviado' end as estado,
                {$evento('EN')} as fecha_envio,
                coalesce({$evento('BO')}, so.soli_fecha_registro_solicitud::timestamp at time zone '".Tramites::ZONA."') as created_at,
                tp.tipa_descripcion as tipo_personal, a.area_descripcion as area_certificacion,
                ({$evento('CA')} is not null) as compromiso_aceptado, {$evento('CA')} as compromiso_aceptado_en,
                so.soli_numero_certificado as certificado_historico_id");
    }

    public function base(Cuenta $usuario): Builder
    {
        $correo = DB::table('comunicacion as cm')->join('tipo_comunicacion as tco', 'tco.tico_id_tipo_comunicacion', '=', 'cm.comu_id_tipo_comunicacion')
            ->whereColumn('cm.comu_id_persona', 'p.pers_id_persona')->where('tco.tico_tipo_comunicacion', config('anfeca_8088.tipos_comunicacion.CORREO'))
            ->orderByDesc('cm.comu_id_comunicacion')->limit(1)->select('cm.comu_valor');
        $datos = DB::table('persona as p')->join('usuario as up', 'up.usua_id_persona', '=', 'p.pers_id_persona')
            ->select(['up.usua_id_usuario as user_id', 'p.pers_id_persona as persona_id', 'p.pers_nombre as nombres',
                'p.pers_apellido_paterno as apellido_paterno', 'p.pers_apellido_materno as apellido_materno', 'p.pers_curp as curp',
                'p.pers_id_institucion as institucion_id'])
            ->selectSub($correo, 'correo_electronico');
        $usuarios = DB::table('usuario as ur')->join('rol as r', 'r.rol_id_rol', '=', 'ur.usua_id_rol')
            ->select(['ur.usua_id_usuario as id', 'r.rol_nombre as rol']);
        $historico = DB::table('solicitud as sh')->select(['sh.soli_id_solicitud as solicitud_id', 'sh.soli_numero_certificado as numero_certificado']);

        $consulta = DB::query()->fromSub(self::proyeccionSolicitudes(), 's')
            ->joinSub($usuarios, 'u', 'u.id', '=', 's.user_id')
            ->leftJoinSub($datos, 'd', 'd.user_id', '=', 's.user_id')
            ->leftJoinSub($this->instituciones->consulta(), 'i', 'i.id', '=', 'd.institucion_id')
            ->leftJoinSub($historico, 'h', 'h.solicitud_id', '=', 's.id')
            ->where('u.rol', Cuenta::ROL_SOLICITANTE);

        $zonas = $this->alcance->zonasAutorizadas($usuario);
        if ($zonas === null) {
            return $consulta;                       // nacional: todo, incluidas las «sin zona»
        }
        if ($zonas === []) {
            return $consulta->whereRaw('1 = 0');    // sin asignación: nada
        }
        $ids = array_keys(array_filter($this->zonas->instituciones(), fn ($r) => in_array($r->numero, $zonas, true)));

        return $consulta->whereIn('i.id', $ids ?: [0]);  // excluye también las de zona no determinable
    }

    /** Columnas de la bandeja y del expediente (sin rutas de archivos). */
    public static function columnas(): array
    {
        return [
            's.id as folio',
            's.estado', 's.tipo_tramite', 's.certificacion', 's.tipo_personal', 's.area_certificacion',
            's.fecha_envio', 's.created_at', 's.compromiso_aceptado',
            DB::raw('null::int as zona_id'), DB::raw('null::varchar as zona_siglas'), DB::raw('null::varchar as zona_descripcion'),
            'i.id as institucion_id', 'i.nombre as institucion', 'i.escuela_facultad as facultad',
            'd.nombres', 'd.apellido_paterno', 'd.apellido_materno',
            'h.numero_certificado as certificado_historico',
        ];
    }

    /** Solicitud dentro del alcance, o null (también si no existe). */
    public function detalle(Cuenta $usuario, int $id): ?object
    {
        $fila = $this->base($usuario)->where('s.id', $id)
            ->select(array_merge(self::columnas(), ['s.user_id', 's.persona_id', 'd.curp', 'd.correo_electronico',
                'i.campus', 'i.zona_anfeca', 's.compromiso_aceptado_en']))
            ->first();
        if (!$fila) {
            return null;
        }
        // Formación de la persona (ESTUDIO_PROFESIONAL / PERSONA_ESTUDIO_PROFESIONAL) y su autorización en esta solicitud.
        $formacion = app(FormacionPdm::class)->cargar((int) $fila->persona_id, (int) $fila->folio);
        foreach (['nivel_origen', 'titulo_origen', 'titulo_origen_otro', 'nivel_ultimo_grado', 'titulo_ultimo_grado',
            'titulo_ultimo_grado_otro', 'titulo_ultimo_grado_especifico', 'origen_titulo_ultimo_grado',
            'titulo_origen_especifico', 'origen_titulo_origen', 'cuenta_con_posgrado'] as $campo) {
            $fila->{$campo} = $formacion?->{$campo};
        }
        $fila->formacion_completada_at = $formacion?->formacion_completada_at?->toIso8601String();

        return $fila;
    }

    public function permite(Cuenta $usuario, int $id): bool
    {
        return $this->base($usuario)->where('s.id', $id)->exists();
    }
}
