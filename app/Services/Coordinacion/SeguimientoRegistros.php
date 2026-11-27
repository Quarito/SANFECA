<?php

namespace App\Services\Coordinacion;

use App\Models\Cuenta as User;
use App\Services\Zonas\ZonasAnfeca;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Seguimiento ADMINISTRATIVO y de SOLO LECTURA del avance de los solicitantes (bloque D).
 *
 * Las etapas y sus condiciones reproducen las reglas REALES del módulo de solicitantes:
 *  - RegistroCompletadoController::requisitos() (lo que el sistema exige para el envío);
 *  - el orden de las pantallas y sus bloqueos (autorización antes de certificación, datos de
 *    refrendo antes de formación, formación autorizada antes de documentos, compromiso antes del envío).
 * La prueba SeguimientoRegistrosTest verifica que «listo para envío» coincide con requisitos().
 *
 * Unidad de la fila: una PERSONA (cuenta de solicitante) con su TRÁMITE ACTUAL
 * (el borrador vigente; si no hay, la solicitud más reciente). Los demás trámites son historial.
 * «Último avance registrado» = fecha más reciente de una captura guardada (nunca un inicio de sesión).
 * Sin bitácora de navegación no se simula «última pantalla abierta».
 */
class SeguimientoRegistros
{
    public const COMPLETADA = 'completada';
    public const EN_PROCESO = 'en_proceso';
    public const PENDIENTE = 'pendiente';
    public const NO_APLICA = 'no_aplica';

    /** Etapas en el orden del flujo del solicitante. */
    public const ETAPAS = [
        'cuenta' => 'Creación de cuenta',
        'datos' => 'Datos personales',
        'autorizacion' => 'Autorización de datos',
        'certificacion' => 'Selección de certificación',
        'certificado' => 'Validación de certificado histórico',
        'refrendo' => 'Datos de refrendo (artículo 13/14)',
        'formacion' => 'Formación académica',
        'probatorios' => 'Documentos probatorios',
        'adicionales' => 'Documentos adicionales del artículo 13/14',
        'compromiso' => 'Aceptación y compromiso',
        'envio' => 'Envío definitivo',
    ];

    public const CLASIFICACIONES = [
        'sin_iniciar' => 'Registrado sin iniciar trámite',
        'en_proceso_sin_solicitud' => 'Registro en proceso sin solicitud formal',
        'borrador' => 'Solicitud en borrador',
        'listo_para_envio' => 'Listo para envío',
        'enviada' => 'Solicitud enviada',
    ];

    /** Documentos probatorios (los académicos se definen en RequisitosFormacion, igual que en el módulo de solicitantes). */
    private const PROBATORIOS = ['constancia_antiguedad_archivo', 'comprobante_pago_archivo'];
    private const ARTICULO = ['solicitud_articulo_14_archivo', 'constancia_articulo_14_archivo'];
    /** Incisos que el sistema actual acepta (ver discrepancia normativa sobre 13.1 en docs/anfeca/normativa). */
    private const INCISOS_SISTEMA = ['13.1', '14', '14.1', '14.2', '14.3'];

    public function __construct(
        private readonly AlcanceZona $alcance,
        private readonly ConsultaSolicitudes $consulta,
        private readonly ZonasAnfeca $zonas,
    ) {
    }

    /** Filas del seguimiento dentro del ALCANCE del usuario (aplicado antes de cualquier filtro o conteo). */
    public function filas(User $usuario): Collection
    {
        $nacional = $this->alcance->esNacional($usuario);
        // Solicitudes visibles: las mismas que la bandeja (zona_id + coordinador_zona para regionales).
        $visibles = $this->consulta->base($usuario)->pluck('s.id')->map(fn ($id) => (int) $id)->flip();

        // Cuentas de solicitante (USUARIO con ROL «solicitante») y sus solicitudes en las 36 tablas oficiales.
        $usuarios = DB::table('usuario as u')->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->where('r.rol_nombre', User::ROL_SOLICITANTE)->orderBy('u.usua_id_usuario')
            ->get(['u.usua_id_usuario as id', 'u.usua_nombre as email', 'u.usua_id_persona as persona_id']);
        $expediente = app(\App\Services\Anfeca\ExpedienteSolicitante::class);
        $tramites = app(\App\Services\Anfeca\Tramites::class);
        $instituciones = app(\App\Services\Anfeca\Instituciones::class)->lista()->keyBy('id');

        $filas = collect();
        foreach ($usuarios as $u) {
            $propias = $tramites->dePersona((int) $u->persona_id);
            $actual = $propias->where('estado', 'borrador')->sortByDesc('id')->first() ?? $propias->sortByDesc('id')->first();

            // Alcance: sin trámite (o trámite fuera de sus zonas) → solo la coordinación nacional.
            if (!$nacional && ($actual === null || !$visibles->has((int) $actual->id))) {
                continue;
            }

            $datos = $expediente->datos((int) $u->id);
            $formacion = $actual?->formacionAcademica;
            $fila = $this->evaluar($u, $datos, $actual, $formacion, $actual?->documentosProbatorios, $actual?->datosRefrendo);

            $institucionId = $datos?->institucion_id;
            $zona = $this->zonas->deInstitucion($institucionId !== null ? (int) $institucionId : null);
            $nombre = trim(collect([$datos?->nombres, $datos?->apellido_paterno, $datos?->apellido_materno])->filter()->implode(' '));
            // USUARIO no registra la fecha de creación de la cuenta: se usa la del primer trámite (INC-06).
            $primera = $propias->sortBy('id')->first()?->created_at;

            $filas->push($fila + [
                'usuario_id' => (int) $u->id,
                'solicitante' => $nombre !== '' ? $nombre : $u->email,
                'correo' => $u->email,
                'folio' => $actual?->id,
                'institucion_id' => $institucionId !== null ? (int) $institucionId : null,
                'institucion' => $institucionId !== null ? $instituciones->get($institucionId)?->nombre : null,
                'zona' => $zona->etiqueta,
                'zona_clave' => $zona->clave,
                'zona_clasificada' => $zona->clasificada(),
                'tipo_tramite' => $actual?->tipo_tramite,
                'certificacion' => $actual?->certificacion,
                'fecha_registro' => $primera,
                'tramites' => $propias->count(),
                'historial' => $propias->reject(fn ($s) => $actual && $s->id === $actual->id)->sortByDesc('id')->values()
                    ->map(fn ($s) => ['folio' => (int) $s->id, 'tipo_tramite' => $s->tipo_tramite, 'certificacion' => $s->certificacion,
                        'certificado' => $s->numero_certificado, 'fecha' => $s->fecha_envio ?? $s->created_at, 'estado' => $s->estado])->all(),
            ]);
        }

        return $filas;
    }

    /** Evalúa etapas, avance, etapa actual, clasificación y último avance de una persona. */
    public function evaluar(object $usuario, ?object $datos, ?object $solicitud, ?object $formacion, ?object $documentos, ?object $refrendo): array
    {
        $refrendoTramite = $solicitud?->tipo_tramite === 'refrendo';
        $articulo = $refrendoTramite && $refrendo !== null && (int) $refrendo->aplica_articulo_14 === 1
            && in_array((string) $refrendo->inciso_articulo_14, self::INCISOS_SISTEMA, true);
        $existe = fn (?object $r, string $campo) => $r !== null && filled($r->{$campo} ?? null) && app(\App\Services\Anfeca\DocumentosPdm::class)->disco()->exists($r->{$campo});
        $estado = fn (bool $completa, bool $iniciada = false) => $completa ? self::COMPLETADA : ($iniciada ? self::EN_PROCESO : self::PENDIENTE);

        // Requisitos centralizados (RequisitosFormacion): con «solo licenciatura» no se exigen datos ni documentos del último grado.
        $formacionCompleta = $formacion !== null
            && collect(\App\Services\Formacion\RequisitosFormacion::campos($formacion))->every(fn ($c) => filled(trim((string) ($formacion->{$c} ?? ''))))
            && collect(\App\Services\Formacion\RequisitosFormacion::documentos($formacion))->every(fn ($c) => $existe($formacion, $c));

        $etapas = [
            'cuenta' => self::COMPLETADA,
            'datos' => $estado($datos !== null),
            'autorizacion' => $estado($datos !== null && (bool) $datos->datos_autorizados),
            'certificacion' => $estado($solicitud !== null && in_array($solicitud->certificacion, array_keys((array) config('anfeca_8088.certificaciones')), true)
                && filled($solicitud->tipo_personal) && filled($solicitud->area_certificacion), $solicitud !== null),
            'certificado' => $refrendoTramite ? $estado($solicitud->certificado_historico_id !== null) : self::NO_APLICA,
            'refrendo' => $refrendoTramite ? $estado($refrendo !== null) : self::NO_APLICA,
            // Completa = datos y 4 documentos académicos (requisitos()) y además autorizada por el solicitante
            // (sin formacion_completada_at el sistema no permite avanzar a documentos probatorios).
            'formacion' => $estado($formacionCompleta && $formacion->formacion_completada_at !== null, $formacion !== null),
            'probatorios' => $estado(collect(self::PROBATORIOS)->every(fn ($c) => $existe($documentos, $c)), $documentos !== null),
            'adicionales' => $articulo ? $estado(collect(self::ARTICULO)->every(fn ($c) => $existe($documentos, $c)), $documentos !== null) : self::NO_APLICA,
            'compromiso' => $estado($solicitud !== null && (bool) $solicitud->compromiso_aceptado),
            'envio' => $estado($solicitud?->estado === 'enviado'),
        ];

        // Avance de CAPTURA: etapas aplicables completadas, sin contar el envío (100 % ≠ enviada).
        $captura = array_filter($etapas, fn ($e, $k) => $k !== 'envio' && $e !== self::NO_APLICA, ARRAY_FILTER_USE_BOTH);
        $completadas = count(array_filter($captura, fn ($e) => $e === self::COMPLETADA));
        $total = count($captura);
        $actual = collect($etapas)->except(['cuenta'])->search(fn ($e) => $e === self::EN_PROCESO || $e === self::PENDIENTE);

        $clasificacion = match (true) {
            $solicitud?->estado === 'enviado' => 'enviada',
            $solicitud !== null && $completadas === $total => 'listo_para_envio',
            $solicitud !== null => 'borrador',
            $datos !== null => 'en_proceso_sin_solicitud',
            default => 'sin_iniciar',
        };

        $fechas = array_filter([
            $datos?->created_at, $datos?->updated_at, $datos?->fecha_autorizacion ?? null,
            $solicitud?->created_at, $solicitud?->updated_at, $solicitud?->compromiso_aceptado_en ?? null, $solicitud?->fecha_envio,
            $formacion?->created_at, $formacion?->updated_at, $formacion?->formacion_completada_at,
            $documentos?->created_at, $documentos?->updated_at, $refrendo?->created_at, $refrendo?->updated_at,
        ]);
        $ultimo = $fechas === [] ? null : collect($fechas)->map(fn ($f) => Carbon::parse($f))->max();

        return [
            'etapas' => $etapas,
            'etapa_actual' => $clasificacion === 'enviada' ? null : ($actual === false ? null : $actual),
            'etapa_actual_etiqueta' => $clasificacion === 'enviada' ? 'Enviada' : ($actual === false ? '—' : self::ETAPAS[$actual]),
            'completadas' => $completadas,
            'total' => $total,
            'avance' => $total === 0 ? 0 : (int) floor($completadas * 100 / $total),
            'clasificacion' => $clasificacion,
            'clasificacion_etiqueta' => self::CLASIFICACIONES[$clasificacion],
            'ultimo_avance' => $ultimo,
        ];
    }

    /** Filtros, orden y paginación sobre filas YA restringidas por alcance. */
    public function filtrar(Collection $filas, array $f): Collection
    {
        $texto = mb_strtolower(trim((string) ($f['q'] ?? '')));

        return $filas
            ->when($texto !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r['solicitante'].' '.$r['correo']), $texto)
                || (ctype_digit($texto) && (int) $texto === $r['folio'])))
            ->when(filled($f['zona'] ?? null), fn ($c) => $c->where('zona_clave', $f['zona']))
            ->when(filled($f['institucion'] ?? null), fn ($c) => $c->where('institucion_id', (int) $f['institucion']))
            ->when(filled($f['tipo_tramite'] ?? null), fn ($c) => $c->where('tipo_tramite', $f['tipo_tramite']))
            ->when(filled($f['certificacion'] ?? null), fn ($c) => $c->where('certificacion', $f['certificacion']))
            ->when(filled($f['estado'] ?? null), fn ($c) => $c->where('clasificacion', $f['estado']))
            ->when(filled($f['etapa'] ?? null), fn ($c) => $c->where('etapa_actual', $f['etapa']))
            ->when(filled($f['registro_desde'] ?? null), fn ($c) => $c->filter(fn ($r) => $r['fecha_registro'] && $r['fecha_registro']->toDateString() >= $f['registro_desde']))
            ->when(filled($f['registro_hasta'] ?? null), fn ($c) => $c->filter(fn ($r) => $r['fecha_registro'] && $r['fecha_registro']->toDateString() <= $f['registro_hasta']))
            ->when(filled($f['avance_desde'] ?? null), fn ($c) => $c->filter(fn ($r) => $r['ultimo_avance'] && $r['ultimo_avance']->toDateString() >= $f['avance_desde']))
            ->when(filled($f['avance_hasta'] ?? null), fn ($c) => $c->filter(fn ($r) => $r['ultimo_avance'] && $r['ultimo_avance']->toDateString() <= $f['avance_hasta']))
            // Días sin avance: SOLO con fecha confiable; no implica abandono.
            ->when(filled($f['sin_avance'] ?? null), fn ($c) => $c->filter(fn ($r) => $r['clasificacion'] !== 'enviada' && $r['ultimo_avance']
                && $r['ultimo_avance']->lte(now()->subDays((int) $f['sin_avance']))))
            ->values();
    }

    /** Indicadores que separan PERSONAS de TRÁMITES (sin duplicidades). */
    public function indicadores(User $usuario): array
    {
        $filas = $this->filas($usuario);
        $porClasificacion = $filas->countBy('clasificacion');
        $tramites = $this->consulta->base($usuario)->selectRaw("
            sum(case when s.estado = 'borrador' then 1 else 0 end) as borradores,
            sum(case when s.estado = 'enviado' then 1 else 0 end) as enviadas,
            sum(case when s.tipo_tramite = 'inicial' then 1 else 0 end) as primera_vez,
            sum(case when s.tipo_tramite = 'refrendo' then 1 else 0 end) as refrendos")->first();

        return [
            'personas' => [
                'registradas' => $filas->count(),
                'sin_iniciar' => (int) ($porClasificacion['sin_iniciar'] ?? 0),
                'en_proceso_sin_solicitud' => (int) ($porClasificacion['en_proceso_sin_solicitud'] ?? 0),
                'listas_para_envio' => (int) ($porClasificacion['listo_para_envio'] ?? 0),
            ],
            'tramites' => [
                'borradores' => (int) $tramites->borradores,
                'enviadas' => (int) $tramites->enviadas,
                'primera_vez' => (int) $tramites->primera_vez,
                'refrendos' => (int) $tramites->refrendos,
            ],
            'por_etapa' => collect(self::ETAPAS)->except(['cuenta'])->map(fn ($etiqueta, $clave) => [
                'clave' => $clave, 'etiqueta' => $etiqueta, 'valor' => $filas->where('etapa_actual', $clave)->count(),
            ])->filter(fn ($e) => $e['valor'] > 0)->values()->all(),
            'por_zona' => $filas->groupBy('zona_clave')->map(fn ($g, $clave) => [
                'clave' => $clave, 'etiqueta' => $g->first()['zona'], 'valor' => $g->count(),
            ])->sortByDesc('valor')->values()->all(),
        ];
    }
}
