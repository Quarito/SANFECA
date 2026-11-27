<?php

namespace App\Services\Anfeca;

use Illuminate\Support\Fluent;

/**
 * Solicitud tal como la consumen controladores, Blade y Vue (mismos nombres que la tabla anterior), con
 * sus «relaciones» calculadas desde las 36 tablas al consultarse: datosRefrendo, formacionAcademica,
 * documentosProbatorios y certificadoHistorico.
 */
class SolicitudVista extends Fluent
{
    public bool $refrendoRegistrado = false;
    public bool $articulo14 = false;
    public ?string $inciso = null;

    private array $relaciones = [];

    public function get($key, $default = null): mixed
    {
        return match ($key) {
            'datosRefrendo' => $this->relaciones[$key] ??= $this->datosRefrendo(),
            'formacionAcademica' => $this->relaciones[$key] ??= app(FormacionPdm::class)->cargar($this->persona_id, $this->id) ?? false,
            'documentosProbatorios' => $this->relaciones[$key] ??= app(DocumentosPdm::class)->probatorios($this->id) ?? false,
            'certificadoHistorico' => $this->relaciones[$key] ??= ($this->numero_certificado
                ? app(HistoricoCertificados::class)->certificado($this->numero_certificado, $this->certificacion) : null) ?? false,
            default => parent::get($key, $default),
        } ?: (in_array($key, ['datosRefrendo', 'formacionAcademica', 'documentosProbatorios', 'certificadoHistorico'], true) ? null : parent::get($key, $default));
    }

    public function __get($key): mixed
    {
        return $this->get($key);
    }

    public function olvidarRelaciones(): void
    {
        $this->relaciones = [];
    }

    private function datosRefrendo(): Fluent|false
    {
        if ($this->tipo_tramite !== 'refrendo' || !$this->refrendoRegistrado) {
            return false;
        }

        return new Fluent([
            'solicitud_id' => $this->id,
            'aplica_articulo_14' => $this->articulo14,
            'inciso_articulo_14' => $this->articulo14 ? $this->inciso : null,
            'refrendo_actual' => app(ConsultaRefrendo::class)->refrendoCorrespondiente($this),
            'numero_refrendo_asignado' => null,
        ]);
    }
}
