<?php

namespace App\Http\Controllers\Coordinacion;

use App\Http\Controllers\Controller;
use App\Services\Coordinacion\AlcanceZona;
use App\Services\Coordinacion\SeguimientoRegistros;
use App\Services\Zonas\ZonasAnfeca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Seguimiento de registros (bloque D): monitoreo ADMINISTRATIVO previo al envío, de SOLO LECTURA.
 * No existen rutas de escritura: la coordinación no modifica datos, no autoriza, no envía ni elimina.
 * El alcance por zona se aplica en SeguimientoRegistros::filas() antes de filtrar o contar.
 */
class SeguimientoController extends Controller
{
    private const ORDEN = ['registro', 'avance_registrado', 'avance', 'solicitante', 'folio'];

    public function __construct(
        private readonly SeguimientoRegistros $seguimiento,
        private readonly AlcanceZona $alcance,
        private readonly ZonasAnfeca $zonas,
    ) {
    }

    public function index(Request $request)
    {
        $usuario = $request->user();
        $nacional = $this->alcance->esNacional($usuario);
        $zonasUsuario = $this->alcance->zonasAutorizadas($usuario);
        $filas = $this->seguimiento->filas($usuario);

        return view('coordinacion.seguimiento', ['opciones' => [
            'zonas' => $this->zonas->opciones($zonasUsuario === null ? null : $this->zonas->clavesDeZonas($zonasUsuario), $nacional),
            'instituciones' => $filas->whereNotNull('institucion_id')->unique('institucion_id')->sortBy('institucion')
                ->map(fn ($r) => ['valor' => (string) $r['institucion_id'], 'etiqueta' => $r['institucion']])->values(),
            'certificaciones' => collect(['ANFECA', 'CACSLA', 'CACECA'])->map(fn ($c) => ['valor' => $c, 'etiqueta' => $c]),
            'tramites' => [['valor' => 'inicial', 'etiqueta' => 'Primera vez'], ['valor' => 'refrendo', 'etiqueta' => 'Refrendo']],
            'estados' => collect(SeguimientoRegistros::CLASIFICACIONES)->map(fn ($e, $k) => ['valor' => $k, 'etiqueta' => $e])->values(),
            'etapas' => collect(SeguimientoRegistros::ETAPAS)->except(['cuenta'])->map(fn ($e, $k) => ['valor' => $k, 'etiqueta' => $e])->values(),
        ], 'filtrosIniciales' => $request->only(['estado', 'etapa', 'zona', 'tipo_tramite', 'sin_avance'])]);
    }

    public function api(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'zona' => ['nullable', 'regex:/^(z[1-7]|c\d{1,9}|sin_zona)$/'],
            'institucion' => ['nullable', 'integer', 'min:1'],
            'tipo_tramite' => ['nullable', Rule::in(['inicial', 'refrendo'])],
            'certificacion' => ['nullable', Rule::in(['ANFECA', 'CACSLA', 'CACECA'])],
            'estado' => ['nullable', Rule::in(array_keys(SeguimientoRegistros::CLASIFICACIONES))],
            'etapa' => ['nullable', Rule::in(array_keys(SeguimientoRegistros::ETAPAS))],
            'registro_desde' => ['nullable', 'date_format:Y-m-d'],
            'registro_hasta' => ['nullable', 'date_format:Y-m-d'],
            'avance_desde' => ['nullable', 'date_format:Y-m-d'],
            'avance_hasta' => ['nullable', 'date_format:Y-m-d'],
            'sin_avance' => ['nullable', Rule::in(['3', '7', '15', '30'])],
            'orden' => ['nullable', Rule::in(self::ORDEN)],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', Rule::in(['10', '25', '50'])],
        ]);

        // Un filtro de zona fuera del alcance se rechaza (no amplía resultados).
        if (filled($f['zona'] ?? null)) {
            $zonas = $this->alcance->zonasAutorizadas($usuario);
            $permitida = $f['zona'] === 'sin_zona' ? $zonas === null : ($zonas === null || in_array($f['zona'], $this->zonas->clavesDeZonas($zonas), true));
            abort_unless($permitida, 403, 'Zona fuera de su alcance.');
        }

        $filas = $this->seguimiento->filtrar($this->seguimiento->filas($usuario), $f);
        $clave = [
            'registro' => fn ($r) => $r['fecha_registro']->timestamp,
            'avance_registrado' => fn ($r) => $r['ultimo_avance']?->timestamp ?? 0,
            'avance' => fn ($r) => $r['avance'],
            'solicitante' => fn ($r) => mb_strtolower($r['solicitante']),
            'folio' => fn ($r) => $r['folio'] ?? 0,
        ][$f['orden'] ?? 'registro'];
        $filas = ($f['direccion'] ?? 'desc') === 'asc' ? $filas->sortBy($clave)->values() : $filas->sortByDesc($clave)->values();

        $porPagina = (int) ($f['por_pagina'] ?? 25);
        $pagina = new LengthAwarePaginator($filas->forPage((int) ($f['pagina'] ?? 1), $porPagina)->values(), $filas->count(), $porPagina, (int) ($f['pagina'] ?? 1));
        $fecha = fn (?Carbon $c) => $c?->timezone('America/Mexico_City')->format('d/m/Y H:i');

        return response()->json([
            'datos' => collect($pagina->items())->values()->map(fn ($r, $i) => [
                'num' => $pagina->firstItem() + $i,
                'folio' => $r['folio'],
                'clave' => $r['usuario_id'],
                'solicitante' => $r['solicitante'],
                'correo' => $r['correo'],
                'institucion' => $r['institucion'],
                'zona' => $r['zona'],
                'zona_clasificada' => $r['zona_clasificada'],
                'tipo_tramite' => $r['tipo_tramite'] === null ? null : ($r['tipo_tramite'] === 'refrendo' ? 'Refrendo' : 'Primera vez'),
                'certificacion' => $r['certificacion'],
                'etapa' => $r['etapa_actual_etiqueta'],
                'completadas' => $r['completadas'],
                'total' => $r['total'],
                'avance' => $r['avance'],
                'fecha_registro' => $fecha($r['fecha_registro']),
                'ultimo_avance' => $fecha($r['ultimo_avance']),
                'clasificacion' => $r['clasificacion'],
                'estado' => $r['clasificacion_etiqueta'],
            ]),
            'meta' => ['total' => $pagina->total(), 'pagina' => $pagina->currentPage(), 'ultima_pagina' => $pagina->lastPage(),
                'por_pagina' => $porPagina, 'desde' => $pagina->firstItem(), 'hasta' => $pagina->lastItem()],
        ]);
    }

    /** Consulta individual: solo si la persona está dentro del alcance (si no, 404). */
    public function show(Request $request, int $usuario)
    {
        $fila = $this->seguimiento->filas($request->user())->firstWhere('usuario_id', $usuario);
        abort_if($fila === null, 404);

        return view('coordinacion.seguimiento-detalle', ['r' => $fila]);
    }
}
