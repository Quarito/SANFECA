<?php

namespace App\Services\Coordinacion;

use App\Models\SolicitudCertificacion;
use Illuminate\Support\Facades\Storage;

/**
 * Documentos de una solicitud y su aplicabilidad según las reglas del trámite.
 * «No aplica» se determina EXCLUSIVAMENTE aquí (art. 13/14 solo en refrendo con aplica_articulo_14,
 * mismo criterio que la pantalla de Documentos probatorios del solicitante). Solo lectura.
 */
class DocumentosAplicables
{
    /** campo => [origen, etiqueta]. Orden = orden de la cuadrícula. */
    public const DOCUMENTOS = [
        'titulo_origen_archivo' => ['formacion', 'Título de licenciatura'],
        'cedula_origen_archivo' => ['formacion', 'Cédula de licenciatura'],
        'titulo_ultimo_grado_archivo' => ['formacion', 'Título del último grado'],
        'cedula_ultimo_grado_archivo' => ['formacion', 'Cédula del último grado'],
        'constancia_antiguedad_archivo' => ['probatorios', 'Constancia de antigüedad'],
        'comprobante_pago_archivo' => ['probatorios', 'Comprobante de pago'],
        'solicitud_articulo_14_archivo' => ['probatorios', 'Solicitud por artículo'],
        'constancia_articulo_14_archivo' => ['probatorios', 'Constancia probatoria de artículo'],
    ];

    public static function existe(string $campo): bool
    {
        return array_key_exists($campo, self::DOCUMENTOS);
    }

    public static function esArticulo(string $campo): bool
    {
        return str_contains($campo, 'articulo_14');
    }

    public function aplicaArticulo13o14(SolicitudCertificacion $solicitud): bool
    {
        $datos = $solicitud->datosRefrendo;

        return $solicitud->tipo_tramite === 'refrendo'
            && $datos !== null
            && (int) $datos->aplica_articulo_14 === 1
            && in_array((string) $datos->inciso_articulo_14, ['13.1', '14', '14.1', '14.2', '14.3'], true);
    }

    /**
     * ¿El documento aplica al trámite? Los del artículo según la regla; los del último grado NO aplican cuando el
     * solicitante declaró que solo cuenta con licenciatura (no se revisan dos veces los mismos documentos).
     */
    public function aplica(SolicitudCertificacion $solicitud, string $campo): bool
    {
        return self::existe($campo) && (!self::esArticulo($campo) || $this->aplicaArticulo13o14($solicitud))
            && \App\Services\Formacion\RequisitosFormacion::documentoAplica($solicitud->formacionAcademica, $campo);
    }

    public function ruta(SolicitudCertificacion $solicitud, string $campo): ?string
    {
        [$origen] = self::DOCUMENTOS[$campo];
        $registro = $origen === 'formacion' ? $solicitud->formacionAcademica : $solicitud->documentosProbatorios;
        $ruta = $registro?->{$campo};

        return filled($ruta) ? (string) $ruta : null;
    }

    /** Estado de carga sin leer el archivo (para la consulta básica). */
    public function estadoCarga(?string $ruta): string
    {
        return match (true) {
            $ruta === null => VersionDocumento::NO_CARGADO,
            $this->rutaSegura($ruta) && Storage::disk('local')->exists($ruta) => VersionDocumento::CARGADO,
            default => VersionDocumento::NO_LOCALIZADO,
        };
    }

    /** Versión vigente calculada desde el ARCHIVO REAL (huella y tamaño leídos en el servidor). */
    public function version(SolicitudCertificacion $solicitud, string $campo): VersionDocumento
    {
        $ruta = $this->ruta($solicitud, $campo);
        if ($ruta === null) {
            return new VersionDocumento(VersionDocumento::NO_CARGADO, null, null, null);
        }

        $disco = Storage::disk('local');
        if (!$this->rutaSegura($ruta) || !$disco->exists($ruta)) {
            return new VersionDocumento(VersionDocumento::NO_LOCALIZADO, $ruta, null, null);
        }

        $flujo = $disco->readStream($ruta);
        $contexto = hash_init('sha256');
        $bytes = hash_update_stream($contexto, $flujo);
        fclose($flujo);

        return new VersionDocumento(VersionDocumento::CARGADO, $ruta, hash_final($contexto), $bytes);
    }

    public function rutaSegura(string $ruta): bool
    {
        return str_starts_with($ruta, 'anfeca/') && !str_contains($ruta, '..');
    }
}
