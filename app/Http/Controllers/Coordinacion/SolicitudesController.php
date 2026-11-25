<?php

namespace App\Http\Controllers\Coordinacion;

use App\Http\Controllers\Controller;
use App\Services\Coordinacion\AlcanceZona;
use App\Services\Coordinacion\ConsultaSolicitudes;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Bandeja de solicitudes (solo lectura). Búsqueda, filtros combinables, orden y
 * paginación en el servidor sobre la consulta con alcance por zona.
 * Un filtro de zona fuera del alcance responde 403 (manipulación de parámetros).
 */
class SolicitudesController extends Controller
{
    /** Ordenamientos permitidos: clave pública => expresión SQL. */
    private const ORDEN = [
        'folio' => 's.id',
        'fecha' => 'coalesce(s.fecha_envio, s.created_at)',
        'nombre' => 'd.apellido_paterno',
        'institucion' => 'i.nombre',
        'zona' => 'z.siglas',
        'estado' => 's.estado',
        'tramite' => 's.tipo_tramite',
        'certificacion' => 's.certificacion',
    ];

    public function __construct(
        private readonly ConsultaSolicitudes $consulta,
        private readonly AlcanceZona $alcance,
    ) {
    }

    public function index(Request $request)
    {
        $usuario = $request->user();
        $nacional = $this->alcance->esNacional($usuario);
        $zonas = $this->alcance->zonasAutorizadas($usuario);

        $opciones = [
            'zonas' => DB::table('zonas')
                ->when($zonas !== null, fn ($q) => $q->whereIn('id', $zonas ?: [0]))
                ->orderBy('siglas')->get(['id', 'siglas'])
                ->map(fn ($z) => ['valor' => (string) $z->id, 'etiqueta' => $z->siglas])
                ->when($nacional, fn ($c) => $c->push(['valor' => 'sin_zona', 'etiqueta' => 'Pendiente de clasificación']))
                ->values(),
            'instituciones' => $this->consulta->base($usuario)
                ->whereNotNull('i.id')->distinct()->orderBy('i.nombre')
                ->get(['i.id', 'i.nombre'])
                ->map(fn ($i) => ['valor' => (string) $i->id, 'etiqueta' => $i->nombre])->values(),
            'certificaciones' => collect(['ANFECA', 'CACSLA', 'CACECA'])->map(fn ($c) => ['valor' => $c, 'etiqueta' => $c]),
            'estados' => [['valor' => 'enviado', 'etiqueta' => 'Enviada'], ['valor' => 'borrador', 'etiqueta' => 'Iniciada']],
            'tramites' => [['valor' => 'inicial', 'etiqueta' => 'Primera vez'], ['valor' => 'refrendo', 'etiqueta' => 'Refrendo']],
        ];

        return view('coordinacion.solicitudes', ['opciones' => $opciones]);
    }

    public function api(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['borrador', 'enviado'])],
            'tipo_tramite' => ['nullable', Rule::in(['inicial', 'refrendo'])],
            'certificacion' => ['nullable', Rule::in(['ANFECA', 'CACSLA', 'CACECA'])],
            'zona' => ['nullable', 'regex:/^(\d{1,9}|sin_zona)$/'],
            'institucion' => ['nullable', 'integer', 'min:1'],
            'orden' => ['nullable', Rule::in(array_keys(self::ORDEN))],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', Rule::in(['10', '25', '50', 10, 25, 50])],
        ]);

        // Manipulación de parámetros: una zona fuera del alcance se rechaza.
        if (filled($f['zona'] ?? null)) {
            $zona = $f['zona'] === 'sin_zona' ? null : (int) $f['zona'];
            abort_unless($this->alcance->permiteZona($usuario, $zona), 403, 'Zona fuera de su alcance.');
        }

        $filtrada = function (bool $conEstado = true) use ($usuario, $f): Builder {
            $q = $this->consulta->base($usuario);
            if (filled($f['q'] ?? null)) {
                $texto = '%'.mb_strtolower(trim($f['q'])).'%';
                $q->where(function (Builder $w) use ($texto, $f) {
                    $w->whereRaw("lower(coalesce(d.nombres, '') || ' ' || coalesce(d.apellido_paterno, '') || ' ' || coalesce(d.apellido_materno, '')) like ?", [$texto])
                        ->orWhereRaw("lower(coalesce(i.nombre, '')) like ?", [$texto])
                        ->orWhereRaw("lower(coalesce(h.numero_certificado, '')) like ?", [$texto]);
                    if (ctype_digit(trim($f['q']))) {
                        $w->orWhere('s.id', (int) trim($f['q']));
                    }
                });
            }
            if ($conEstado && filled($f['estado'] ?? null)) {
                $q->where('s.estado', $f['estado']);
            }
            foreach (['tipo_tramite' => 's.tipo_tramite', 'certificacion' => 's.certificacion'] as $campo => $columna) {
                if (filled($f[$campo] ?? null)) {
                    $q->where($columna, $f[$campo]);
                }
            }
            if (filled($f['institucion'] ?? null)) {
                $q->where('i.id', (int) $f['institucion']);
            }
            if (filled($f['zona'] ?? null)) {
                $f['zona'] === 'sin_zona' ? $q->whereNull('i.zona_id') : $q->where('i.zona_id', (int) $f['zona']);
            }

            return $q;
        };

        $orden = self::ORDEN[$f['orden'] ?? 'fecha'];
        $direccion = $f['direccion'] ?? 'desc';
        $porPagina = (int) ($f['por_pagina'] ?? 25);

        $pagina = $filtrada()
            ->select(ConsultaSolicitudes::columnas())
            ->orderByRaw("{$orden} {$direccion} nulls last")
            ->orderBy('s.id', $direccion)
            ->paginate($porPagina, ['*'], 'pagina', (int) ($f['pagina'] ?? 1));

        // Contadores por estado con los mismos filtros (salvo el propio estado).
        $porEstado = $filtrada(false)
            ->select('s.estado')->selectRaw('count(*) as total')->groupBy('s.estado')
            ->pluck('total', 's.estado');

        return response()->json([
            // «num»: consecutivo VISUAL (no es folio ni id). Continúa entre páginas y se
            // recalcula con filtros, búsqueda y orden porque se deriva de la posición en el resultado.
            'datos' => collect($pagina->items())->values()->map(fn ($r, $i) => [
                'num' => $pagina->firstItem() + $i,
                'folio' => $r->folio,
                'zona' => $r->zona_siglas,
                'nombre' => trim(implode(' ', array_filter([$r->nombres, $r->apellido_paterno, $r->apellido_materno]))) ?: null,
                'institucion' => $r->institucion,
                'facultad' => $r->facultad,
                'tramite' => $r->tipo_tramite === 'refrendo' ? 'Refrendo' : 'Primera vez',
                'certificacion' => $r->certificacion,
                'tipo_personal' => $r->tipo_personal,
                'area' => $r->area_certificacion,
                'certificado_historico' => $r->tipo_tramite === 'refrendo' ? $r->certificado_historico : null,
                'fecha_envio' => $r->fecha_envio ? Carbon::parse($r->fecha_envio)->timezone('America/Mexico_City')->format('d/m/Y H:i') : null,
                'estado' => $r->estado,
            ]),
            'meta' => [
                'total' => $pagina->total(),
                'pagina' => $pagina->currentPage(),
                'ultima_pagina' => $pagina->lastPage(),
                'por_pagina' => $pagina->perPage(),
                'desde' => $pagina->firstItem(),
                'hasta' => $pagina->lastItem(),
                'por_estado' => [
                    'enviado' => (int) ($porEstado['enviado'] ?? 0),
                    'borrador' => (int) ($porEstado['borrador'] ?? 0),
                ],
            ],
        ]);
    }
}
