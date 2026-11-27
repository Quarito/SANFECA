<?php

namespace App\Http\Controllers\Coordinacion;

use App\Http\Controllers\Controller;
use App\Models\Pdm\Institucion;
use App\Models\Pdm\Solicitud;
use App\Services\Pdm\Solicitantes\ConsultaExpedientesPdm;
use App\Services\Zonas\ResolucionZona;
use App\Services\Zonas\ZonasAnfeca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BandejaPdmController extends Controller
{
    public function __construct(private readonly ConsultaExpedientesPdm $consulta, private readonly ZonasAnfeca $zonas) {}

    public function index()
    {
        return view('coordinacion.solicitudes', ['opciones' => [
            'instituciones' => Institucion::orderBy('inst_nombre')->get()
                ->map(fn ($i) => ['valor' => (string) $i->getKey(), 'etiqueta' => $i->inst_nombre]),
            'zonas' => collect($this->zonas->oficiales())->keys()
                ->map(fn ($n) => ['valor' => "z{$n}", 'etiqueta' => $this->zonas->etiquetaOficial($n)])
                ->push(['valor' => 'sin_zona', 'etiqueta' => 'Sin clasificar']),
            'estados' => [['valor' => 'enviado', 'etiqueta' => 'Enviada'], ['valor' => 'borrador', 'etiqueta' => 'Iniciada']],
            'certificaciones' => [],
            'tramites' => [],
        ], 'soloConsultaPdm' => true]);
    }

    public function api(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['borrador', 'enviado'])],
            'institucion' => ['nullable', 'integer', 'between:1,2147483647'],
            'zona' => ['nullable', 'regex:/^(z[1-7]|sin_zona)$/'],
            'orden' => ['nullable', Rule::in(['folio', 'fecha', 'nombre', 'institucion', 'zona', 'estado', 'tramite', 'certificacion'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', Rule::in([10, 25, 50])],
            'certificacion' => ['prohibited'],
            'tipo_tramite' => ['prohibited'],
        ]);
        $query = $this->consulta->consulta()->select('anfeca_modelo.solicitud.*')
            ->join('anfeca_modelo.persona as p', 'p.pers_id_persona', '=', 'soli_id_persona')
            ->leftJoin('anfeca_modelo.institucion as i', 'i.inst_id_institucion', '=', 'p.pers_id_institucion');

        if (filled($filtros['q'] ?? null)) {
            $texto = trim($filtros['q']);
            $query->where(function ($buscar) use ($texto) {
                $patron = '%'.strtr($texto, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $buscar->whereRaw("concat_ws(' ', p.pers_nombre, p.pers_apellido_paterno, p.pers_apellido_materno, i.inst_nombre) ilike ? escape '!'", [$patron]);
                if (ctype_digit($texto) && strlen($texto) <= 10 && (int) $texto <= 2147483647) {
                    $buscar->orWhere('soli_folio_solicitud', (int) $texto);
                }
            });
        }
        if (filled($filtros['institucion'] ?? null)) {
            $query->where('i.inst_id_institucion', $filtros['institucion']);
        }
        if (filled($filtros['zona'] ?? null)) {
            $ids = Institucion::all(['inst_id_institucion', 'inst_zona'])
                ->filter(fn ($i) => $this->zona($i->inst_zona)->clave === $filtros['zona'])->modelKeys();
            $query->where(fn ($w) => $filtros['zona'] === 'sin_zona'
                ? $w->whereNull('i.inst_id_institucion')->orWhereIn('i.inst_id_institucion', $ids)
                : $w->whereIn('i.inst_id_institucion', $ids));
        }
        $codigos = ['enviado' => ['EN'], 'borrador' => ['BO', 'FA', 'CA']];
        $porEstado = collect($codigos)->map(fn (array $siglas) => (clone $query)
            ->whereHas('ultimoEstado', fn ($q) => $q->whereIn('esso_siglas', $siglas))->count());
        if (isset($filtros['estado'])) {
            $query->whereHas('ultimoEstado', fn ($q) => $q->whereIn('esso_siglas', $codigos[$filtros['estado']]));
        }

        $orden = [
            'folio' => 'soli_folio_solicitud', 'nombre' => 'p.pers_apellido_paterno',
            'institucion' => 'i.inst_nombre', 'zona' => 'i.inst_zona',
        ][$filtros['orden'] ?? 'fecha'] ?? 'soli_fecha_registro_solicitud';
        $direccion = $filtros['direccion'] ?? 'desc';
        $pagina = $query->orderBy($orden, $direccion)->orderBy('soli_id_solicitud', $direccion)
            ->paginate((int) ($filtros['por_pagina'] ?? 25), ['*'], 'pagina');

        return response()->json([
            'datos' => collect($pagina->items())->values()->map(fn (Solicitud $s, int $i) => $this->fila($s, $pagina->firstItem() + $i)),
            'meta' => ['total' => $pagina->total(), 'pagina' => $pagina->currentPage(), 'ultima_pagina' => $pagina->lastPage(),
                'por_pagina' => $pagina->perPage(), 'desde' => $pagina->firstItem(), 'hasta' => $pagina->lastItem(), 'por_estado' => $porEstado],
        ]);
    }

    private function fila(Solicitud $s, int $num): array
    {
        $institucion = $s->persona->institucion;
        $zona = $this->zona($institucion?->inst_zona);
        $estado = $this->consulta->estado($s);

        return [
            'id' => $s->getKey(), 'folio' => $s->soli_folio_solicitud, 'num' => $num,
            'zona' => $zona->etiqueta, 'zona_clasificada' => $zona->clasificada(),
            'nombre' => implode(' ', array_filter([$s->persona->pers_nombre, $s->persona->pers_apellido_paterno, $s->persona->pers_apellido_materno])),
            'institucion' => $institucion?->inst_nombre, 'facultad' => $institucion?->inst_facultad,
            'tramite' => $s->convocatoria?->tipoConvocatoria?->tico_tipo_convocatoria,
            'certificacion' => null, 'tipo_personal' => $s->tipoPersonalAcademico->tipa_descripcion,
            'area' => $s->areaCertificacion->area_descripcion, 'certificado_historico' => null,
            'fecha_envio' => $estado === 'enviado' ? $this->consulta->fechaEstado($s) : null,
            'estado' => $estado, 'estado_etiqueta' => $s->ultimoEstado?->esso_descripcion ?? 'Sin estado registrado',
        ];
    }

    private function zona(?string $zona): ResolucionZona
    {
        return $this->zonas->resolver(null, null, null, $zona);
    }
}
