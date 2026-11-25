<?php

namespace App\Http\Controllers\Coordinacion;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Coordinacion\AlcanceZona;
use App\Services\Coordinacion\ConsultaSolicitudes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores del dashboard, calculados en PostgreSQL con la MISMA consulta de
 * alcance que la bandeja (ConsultaSolicitudes). No hay valores fijos ni ficticios:
 * los indicadores sin estructura que los respalde se informan como no implementados
 * (valor null), nunca como cero.
 */
class IndicadoresController extends Controller
{
    /** Estados de evaluación previstos para la fase 6.4 (aún sin estructura). */
    private const EVALUACION_PENDIENTE = [
        'por_revisar' => 'Por revisar',
        'en_evaluacion' => 'En evaluación',
        'con_observaciones' => 'Con observaciones',
        'aceptadas' => 'Aceptadas',
        'no_aceptadas' => 'No aceptadas',
        'avance_evaluacion' => 'Avance de evaluación',
    ];

    public function __construct(
        private readonly ConsultaSolicitudes $consulta,
        private readonly AlcanceZona $alcance,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $nacional = $this->alcance->esNacional($usuario);
        $base = fn () => $this->consulta->base($usuario);

        $totales = $base()->selectRaw("
            count(*) as total,
            sum(case when s.estado = 'borrador' then 1 else 0 end) as iniciadas,
            sum(case when s.estado = 'enviado' then 1 else 0 end) as enviadas,
            sum(case when s.tipo_tramite = 'inicial' then 1 else 0 end) as primera_vez,
            sum(case when s.tipo_tramite = 'refrendo' then 1 else 0 end) as refrendos,
            count(distinct s.user_id) as solicitantes_con_solicitud
        ")->first();

        // Usuarios registrados: cuentas de solicitantes (nunca cuentas de coordinación).
        // La coordinación de zona solo puede atribuir usuarios a su zona a través de sus solicitudes.
        $usuarios = $nacional
            ? DB::table('users')->where(fn ($q) => $q->whereNull('rol')->orWhereNotIn('rol', User::ROLES_COORDINACION))->count()
            : (int) $totales->solicitantes_con_solicitud;

        return response()->json([
            'alcance' => [
                'nacional' => $nacional,
                'descripcion' => $this->alcance->descripcion($usuario),
            ],
            'generado_en' => now()->timezone('America/Mexico_City')->format('d/m/Y H:i'),
            'tarjetas' => [
                ['clave' => 'usuarios', 'etiqueta' => $nacional ? 'Usuarios registrados' : 'Solicitantes en sus zonas',
                    'valor' => $usuarios,
                    'nota' => $nacional ? 'Cuentas de solicitantes; excluye coordinación' : 'Personas con al menos una solicitud en sus zonas'],
                ['clave' => 'total', 'etiqueta' => 'Solicitudes', 'valor' => (int) $totales->total,
                    'nota' => 'Iniciadas y enviadas'],
                ['clave' => 'iniciadas', 'etiqueta' => 'Solicitudes iniciadas', 'valor' => (int) $totales->iniciadas,
                    'nota' => 'En captura; no enviadas'],
                ['clave' => 'enviadas', 'etiqueta' => 'Solicitudes enviadas', 'valor' => (int) $totales->enviadas,
                    'nota' => 'Envío confirmado por el solicitante'],
                ['clave' => 'primera_vez', 'etiqueta' => 'Primera vez', 'valor' => (int) $totales->primera_vez,
                    'nota' => 'Certificación inicial'],
                ['clave' => 'refrendos', 'etiqueta' => 'Refrendos', 'valor' => (int) $totales->refrendos,
                    'nota' => 'Incluye artículo 13/14'],
            ],
            'evaluacion' => collect(self::EVALUACION_PENDIENTE)->map(fn ($etiqueta, $clave) => [
                'clave' => $clave, 'etiqueta' => $etiqueta, 'implementado' => false, 'valor' => null,
                'nota' => 'Pendiente de implementación (fase 6.4)',
            ])->values(),
            'convocatoria' => ['implementado' => false, 'nota' => 'Asociación con convocatorias pendiente de validación'],
            'distribucion' => [
                'zona' => $this->distribucionZona($base()),
                'institucion' => $this->distribucion($base(), 'i.nombre', 'Sin institución registrada'),
                'certificacion' => $this->distribucion($base(), 's.certificacion', 'Sin certificación'),
                'tipo_tramite' => $this->distribucion($base(), 's.tipo_tramite', 'Sin tipo',
                    fn ($v) => $v === 'refrendo' ? 'Refrendo' : ($v === 'inicial' ? 'Primera vez' : $v)),
            ],
            'recientes' => $base()
                ->select('s.id as folio', 's.estado', 's.tipo_tramite', 's.certificacion', 'i.nombre as institucion',
                    'z.siglas as zona', 's.fecha_envio', 's.created_at')
                ->orderByRaw('coalesce(s.fecha_envio, s.created_at) desc')
                ->orderByDesc('s.id')
                ->limit(5)
                ->get()
                ->map(fn ($r) => [
                    'folio' => $r->folio,
                    'estado' => $r->estado,
                    'tramite' => $r->tipo_tramite === 'refrendo' ? 'Refrendo' : 'Primera vez',
                    'certificacion' => $r->certificacion,
                    'institucion' => $r->institucion,
                    'zona' => $r->zona,
                    'fecha' => optional($r->fecha_envio ?? $r->created_at, fn ($f) => \Illuminate\Support\Carbon::parse($f)
                        ->timezone('America/Mexico_City')->format('d/m/Y H:i')),
                ]),
        ]);
    }

    /** Agrupa por la columna (expresión idéntica en SELECT y GROUP BY) y rotula los vacíos en PHP. */
    private function distribucion($consulta, string $columna, string $vacio, ?callable $rotulo = null)
    {
        return $consulta
            ->select($columna.' as etiqueta')
            ->selectRaw('count(*) as valor')
            ->groupBy($columna)
            ->get()
            ->map(fn ($r) => [
                'etiqueta' => $r->etiqueta === null ? $vacio : ($rotulo ? $rotulo($r->etiqueta) : $r->etiqueta),
                'valor' => (int) $r->valor,
            ])
            ->sortBy([['valor', 'desc'], ['etiqueta', 'asc']])
            ->values();
    }

    private function distribucionZona($consulta)
    {
        return $consulta
            ->selectRaw('z.siglas as etiqueta, count(*) as valor')
            ->groupBy('z.siglas')
            ->orderByDesc('valor')
            ->get()
            ->map(fn ($r) => [
                'etiqueta' => $r->etiqueta ?? 'Pendiente de clasificación',
                'valor' => (int) $r->valor,
                'sin_clasificar' => $r->etiqueta === null,
            ]);
    }
}
