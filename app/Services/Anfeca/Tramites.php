<?php

namespace App\Services\Anfeca;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

/**
 * Solicitudes sobre SOLICITUD, ESTADO_SOLICITUD, C_ESTADO_SOLICITUD, CONVOCATORIA/TIPO_CONVOCATORIA,
 * AREA_CERTIFICACION, TIPO_PERSONAL_ACADEMICO y ARTICULO_SUBINDICE.
 *
 *  certificación (ANFECA/CACSLA/CACECA) → SOLI_ID_CONVOCATORIA → TIPO_CONVOCATORIA.TICO_SIGLAS
 *  tipo de trámite                      → refrendo si SOLI_NUMERO_CERTIFICADO (folio histórico) está registrado
 *  estado y fechas                      → historial ESTADO_SOLICITUD (Borrador, Datos de refrendo registrados,
 *                                         Formación académica autorizada, Documentos probatorios registrados,
 *                                         Compromiso de entrega aceptado, Enviada) con fecha y hora
 *  artículo 14 / inciso                 → SOLI_ART14, SOLI_ART13 (inciso 13.1) y SOLI_ID_ARTICULO
 *  folio                                → SOLI_FOLIO_SOLICITUD = SOLI_ID_SOLICITUD
 */
final class Tramites
{
    public const ZONA = 'America/Mexico_City';

    /** Proyección de una o varias solicitudes con los nombres de campo de las pantallas. */
    public function consulta(): \Illuminate\Database\Query\Builder
    {
        $evento = fn (string $siglas, string $alias) => DB::table('estado_solicitud as e')
            ->whereColumn('e.esso_id_solicitud', 's.soli_id_solicitud')->where('e.esso_siglas', $siglas)
            ->selectRaw("min(e.esso_fecha + e.esso_hora)");

        return DB::table('solicitud as s')
            ->join('usuario as u', 'u.usua_id_persona', '=', 's.soli_id_persona')
            ->leftJoin('convocatoria as c', 'c.conv_id_convocatoria', '=', 's.soli_id_convocatoria')
            ->leftJoin('tipo_convocatoria as tc', 'tc.tico_id_tipo_convocatoria', '=', 'c.conv_id_tipo_convocatoria')
            ->join('area_certificacion as a', 'a.area_id_area_certificacion', '=', 's.soli_id_area_certificacion')
            ->join('tipo_personal_academico as tp', 'tp.tipa_id_tipo_personal_academico', '=', 's.soli_id_tipo_personal_academico')
            ->leftJoin('articulo_subindice as ar', 'ar.arce_id_articulo', '=', 's.soli_id_articulo')
            ->select([
                's.soli_id_solicitud as id', 'u.usua_id_usuario as user_id', 's.soli_id_persona as persona_id',
                's.soli_folio_solicitud as folio', 'tc.tico_siglas as certificacion',
                'a.area_descripcion as area_certificacion', 'tp.tipa_descripcion as tipo_personal',
                's.soli_numero_certificado as numero_certificado', 's.soli_art13', 's.soli_art14',
                'ar.arce_subindice as inciso', 's.soli_fecha_registro_solicitud as fecha_registro',
            ])
            ->selectSub($evento('EN', 'en'), 'enviada_en')
            ->selectSub($evento('CA', 'ca'), 'compromiso_en')
            ->selectSub($evento('RF', 'rf'), 'refrendo_en')
            ->selectSub($evento('BO', 'bo'), 'creada_en');
    }

    /** @return Collection<int, Fluent> */
    public function dePersona(int $persona): Collection
    {
        return $this->consulta()->where('s.soli_id_persona', $persona)->orderBy('s.soli_id_solicitud')->get()->map(fn ($f) => $this->vista($f));
    }

    public function porId(int $id): ?Fluent
    {
        $fila = $this->consulta()->where('s.soli_id_solicitud', $id)->first();

        return $fila ? $this->vista($fila) : null;
    }

    /** Borrador activo de la persona (la solicitud sin evento «Enviada» más reciente). */
    public function borrador(int $persona): ?Fluent
    {
        return $this->dePersona($persona)->where('estado', 'borrador')->sortByDesc('id')->first();
    }

    public function ultimaEnviada(int $persona): ?Fluent
    {
        return $this->dePersona($persona)->where('estado', 'enviado')->sortByDesc('id')->first();
    }

    private function momento(?string $valor): ?Carbon
    {
        return $valor ? Carbon::parse($valor, self::ZONA) : null;
    }

    private function vista(object $f): Fluent
    {
        $esRefrendo = filled($f->numero_certificado);
        $solicitud = new SolicitudVista([
            'id' => (int) $f->id,
            'user_id' => (int) $f->user_id,
            'persona_id' => (int) $f->persona_id,
            'folio' => (int) $f->folio,
            'certificacion' => $f->certificacion,
            'tipo_tramite' => $esRefrendo ? 'refrendo' : 'inicial',
            'estado' => $f->enviada_en ? 'enviado' : 'borrador',
            'fecha_envio' => $this->momento($f->enviada_en),
            'created_at' => $this->momento($f->creada_en) ?? $this->momento($f->fecha_registro),
            'tipo_personal' => $f->tipo_personal,
            'area_certificacion' => Catalogo::areaInterna($f->area_certificacion),   // nombre interno de las reglas
            'compromiso_aceptado' => $f->compromiso_en !== null,
            'compromiso_aceptado_en' => $this->momento($f->compromiso_en),
            'numero_certificado' => $f->numero_certificado,
            'certificado_historico_id' => $esRefrendo ? ((int) ltrim((string) $f->numero_certificado, '0') ?: 0) : null,
        ]);
        $solicitud->refrendoRegistrado = $f->refrendo_en !== null;
        $solicitud->articulo14 = (bool) $f->soli_art14;
        $solicitud->inciso = $f->inciso;

        return $solicitud;
    }

    /** Momento del primer evento con esas siglas, o null. */
    public function momentoEvento(int $solicitud, string $siglas): ?Carbon
    {
        return $this->momento(DB::table('estado_solicitud')->where('esso_id_solicitud', $solicitud)->where('esso_siglas', $siglas)
            ->selectRaw('min(esso_fecha + esso_hora) as m')->value('m'));
    }

    public function registrarEvento(int $solicitud, string $siglas, ?string $motivo = null): void
    {
        [$id, $descripcion] = Catalogo::estadoSolicitud($siglas);
        $ahora = now(self::ZONA);
        DB::table('estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => $id,
            'esso_id_solicitud' => $solicitud,
            'esso_descripcion' => mb_substr($descripcion, 0, 55),
            'esso_siglas' => $siglas,
            'esso_fecha' => $ahora->toDateString(),
            'esso_hora' => $ahora->format('H:i:s'),
            'esso_motivo_rechazo' => $motivo,
        ]);
    }

    public function tieneEvento(int $solicitud, string $siglas): bool
    {
        return DB::table('estado_solicitud')->where('esso_id_solicitud', $solicitud)->where('esso_siglas', $siglas)->exists();
    }

    /** Bloquea la fila SOLICITUD (FOR UPDATE) y devuelve su estado actual. */
    public function bloquear(int $solicitud): ?Fluent
    {
        DB::table('solicitud')->where('soli_id_solicitud', $solicitud)->lockForUpdate()->first();

        return $this->porId($solicitud);
    }

    /** Nueva solicitud en borrador (folio = id). */
    public function crear(int $persona, string $certificacion, string $tipoPersonal, string $area, ?string $folioHistorico): int
    {
        $id = (int) DB::selectOne("select nextval(pg_get_serial_sequence('solicitud', 'soli_id_solicitud')) as id")->id;
        DB::table('solicitud')->insert([
            'soli_id_solicitud' => $id,
            'soli_id_persona' => $persona,
            'soli_id_area_certificacion' => $this->idArea($area),
            'soli_id_tipo_personal_academico' => $this->idTipoPersonal($tipoPersonal),
            'soli_id_convocatoria' => Catalogo::convocatoria($certificacion),
            'soli_id_articulo' => null,
            'soli_numero_certificado' => $folioHistorico,
            'soli_fecha_registro_solicitud' => now(self::ZONA)->toDateString(),
            'soli_folio_solicitud' => $id,
            'soli_art13' => false,
            'soli_art14' => false,
        ]);
        $this->registrarEvento($id, 'BO');

        return $id;
    }

    public function actualizarSeleccion(int $solicitud, string $tipoPersonal, string $area): void
    {
        DB::table('solicitud')->where('soli_id_solicitud', $solicitud)->update([
            'soli_id_area_certificacion' => $this->idArea($area),
            'soli_id_tipo_personal_academico' => $this->idTipoPersonal($tipoPersonal),
        ]);
    }

    public function guardarRefrendo(int $solicitud, bool $articulo14, ?string $inciso): void
    {
        DB::table('solicitud')->where('soli_id_solicitud', $solicitud)->update([
            'soli_art14' => $articulo14,
            'soli_art13' => $articulo14 && $inciso === '13.1',
            'soli_id_articulo' => $articulo14 && $inciso ? Catalogo::articulo($inciso) : null,
        ]);
        if (!$this->tieneEvento($solicitud, 'RF')) {
            $this->registrarEvento($solicitud, 'RF');
        }
    }

    private function idArea(string $area): int
    {
        return Catalogo::area($area) ?? throw \Illuminate\Validation\ValidationException::withMessages(['area_certificacion' => 'Seleccione un área de certificación válida.']);
    }

    private function idTipoPersonal(string $tipo): int
    {
        return Catalogo::tipoPersonal($tipo) ?? throw \Illuminate\Validation\ValidationException::withMessages(['tipo_personal' => 'Seleccione un tipo de personal académico válido.']);
    }
}
