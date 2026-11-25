<?php

namespace App\Services\Coordinacion;

use App\Enums\EstadoRevision;
use App\Enums\ResultadoRevision;
use App\Exceptions\Coordinacion\ConflictoRevision;
use App\Models\RevisionDocumental as Revision;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Revisión documental de la coordinación (fase 6.3.1).
 * - Solo INSERTA en revisiones_documentales; nunca modifica la solicitud ni sus documentos.
 * - No genera dictamen, puntaje ni aprobación.
 * - La autorización (Policy + alcance por zona) la verifica el controlador en CADA petición.
 */
class RevisionDocumental
{
    public function __construct(private readonly DocumentosAplicables $documentos)
    {
    }

    /**
     * Estado de revisión de todos los documentos y los indicadores de avance.
     * Usa exclusivamente datos persistidos y la versión real de cada archivo.
     */
    public function resumen(SolicitudCertificacion $solicitud): array
    {
        $vigentes = Revision::query()->where('solicitud_id', $solicitud->id)->vigentes()->get()->keyBy('documento_clave');
        $filas = [];

        foreach (DocumentosAplicables::DOCUMENTOS as $campo => [, $etiqueta]) {
            $aplica = $this->documentos->aplica($solicitud, $campo);
            $ruta = $this->documentos->ruta($solicitud, $campo);
            if (!$aplica && $ruta === null) {
                $filas[$campo] = ['campo' => $campo, 'etiqueta' => $etiqueta, 'aplica' => false,
                    'carga' => VersionDocumento::NO_CARGADO, 'estado' => EstadoRevision::NoAplica->value, 'vigente' => null, 'version' => null];
                continue;
            }

            $version = $this->documentos->version($solicitud, $campo);
            $vigente = $vigentes->get($campo);
            $estado = match (true) {
                !$aplica => EstadoRevision::NoAplica,
                $vigente !== null && $vigente->version()->igualA($version) => EstadoRevision::desdeResultado($vigente->resultado),
                default => EstadoRevision::PorRevisar,
            };

            $filas[$campo] = [
                'campo' => $campo,
                'etiqueta' => $etiqueta,
                'aplica' => $aplica,
                'carga' => $version->estado,
                'estado' => $estado->value,
                'vigente' => $vigente ? $this->publica($vigente) : null,
                'vigente_de_otra_version' => $vigente !== null && !$vigente->version()->igualA($version),
                'version' => $version->publica(),
            ];
        }

        return ['documentos' => $filas, 'indicadores' => $this->indicadores($filas)];
    }

    /** Indicadores: los no aplicables se excluyen de todos los denominadores. */
    public function indicadores(array $filas): array
    {
        $aplicables = array_filter($filas, fn ($f) => $f['aplica']);
        $total = count($aplicables);
        $cuenta = fn (string $estado) => count(array_filter($aplicables, fn ($f) => $f['estado'] === $estado));
        $cargados = count(array_filter($aplicables, fn ($f) => $f['carga'] === VersionDocumento::CARGADO));
        $porRevisar = $cuenta(EstadoRevision::PorRevisar->value);
        $validados = $cuenta(EstadoRevision::Validado->value);
        $porcentaje = fn (int $n) => $total === 0 ? 0 : (int) round($n * 100 / $total);

        return [
            'aplicables' => $total,
            'cargados' => $cargados,
            'faltantes' => $total - $cargados,
            'por_revisar' => $porRevisar,
            'validados' => $validados,
            'con_observaciones' => $cuenta(EstadoRevision::ConObservaciones->value),
            'no_validos' => $cuenta(EstadoRevision::NoValido->value),
            'porcentaje_revisado' => $porcentaje($total - $porRevisar),
            'porcentaje_validado' => $porcentaje($validados),
        ];
    }

    /** Documento, versión vigente, revisión vigente e historial (con consecutivo «num»). */
    public function detalle(SolicitudCertificacion $solicitud, string $campo): array
    {
        $version = $this->documentos->version($solicitud, $campo);
        $historial = Revision::query()->delDocumento($solicitud->id, $campo)->orderBy('id')->get();
        $vigente = Revision::query()->delDocumento($solicitud->id, $campo)->vigentes()->first();

        return [
            'campo' => $campo,
            'etiqueta' => DocumentosAplicables::DOCUMENTOS[$campo][1],
            'solicitud_estado' => $solicitud->estado,
            'version' => $version->publica(),
            'vigente' => $vigente ? $this->publica($vigente) : null,
            'vigente_de_esta_version' => $vigente !== null && $vigente->version()->igualA($version),
            'historial' => $historial->values()->map(fn (Revision $r, int $i) => ['num' => $i + 1] + $this->publica($r))->all(),
        ];
    }

    /**
     * Registra una revisión. Devuelve [revisión, creada?]. Un reintento idéntico (misma clave de
     * idempotencia y mismo contenido) devuelve la existente con creada = false.
     *
     * @param  array{resultado:string, observaciones:?string, motivo_cambio:?string, revision_vigente_id:?int, version:string, clave_idempotencia:string}  $datos
     *
     * @throws ConflictoRevision  (409) y ValidationException (422)
     */
    public function registrar(User $coordinador, SolicitudCertificacion $solicitud, string $campo, array $datos): array
    {
        $resultado = ResultadoRevision::from($datos['resultado']);
        $observaciones = $this->texto($datos['observaciones'] ?? null);
        $motivo = $this->texto($datos['motivo_cambio'] ?? null);
        $anteriorEnviada = isset($datos['revision_vigente_id']) ? (int) $datos['revision_vigente_id'] : null;

        try {
            return DB::transaction(function () use ($coordinador, $solicitud, $campo, $datos, $resultado, $observaciones, $motivo, $anteriorEnviada) {
                // Bloqueo COMPARTIDO de la solicitud (no la modifica): serializa la revisión frente a
                // cambios de estado o de documentos, que toman FOR UPDATE sobre la misma fila.
                $bloqueada = SolicitudCertificacion::query()->whereKey($solicitud->id)->sharedLock()->firstOrFail();

                // 1. Idempotencia: misma clave ⇒ mismo coordinador, solicitud, documento, versión y contenido.
                $reintento = function () use ($datos, $coordinador, $bloqueada, $campo, $resultado, $observaciones, $motivo, $anteriorEnviada): ?array {
                    $previa = Revision::query()->where('clave_idempotencia', $datos['clave_idempotencia'])->first();
                    if ($previa === null) {
                        return null;
                    }
                    $identica = (int) $previa->coordinador_id === (int) $coordinador->id
                        && (int) $previa->solicitud_id === (int) $bloqueada->id
                        && $previa->documento_clave === $campo
                        && hash_equals($previa->version()->token(), (string) $datos['version'])
                        && $previa->resultado === $resultado
                        && $previa->observaciones === $observaciones
                        && $previa->motivo_cambio === $motivo
                        && ($previa->revision_anterior_id === null ? null : (int) $previa->revision_anterior_id) === $anteriorEnviada;

                    if (!$identica) {
                        throw new ConflictoRevision('idempotencia', 'La clave de la petición ya se utilizó con información diferente. Actualice el documento e intente de nuevo.');
                    }

                    return [$previa, false];
                };
                if (($previa = $reintento()) !== null) {
                    return $previa;
                }

                // 2. Solo solicitudes enviadas (estado actual, no el que vio el navegador).
                if ($bloqueada->estado !== 'enviado') {
                    throw new ConflictoRevision('estado', 'La solicitud no está enviada; no admite revisión documental.');
                }

                // 3. Concurrencia (optimista): la revisión vigente debe ser la que consultó el coordinador.
                //    Si dos peticiones pasan esta comprobación a la vez, la base rechaza la segunda
                //    (rd_sucesor_unico_uq / rd_primera_revision_uq) y se responde 409. No se toman
                //    bloqueos de fila sobre el historial: requerirían privilegio UPDATE sobre la tabla.
                $vigente = Revision::query()->delDocumento($bloqueada->id, $campo)->vigentes()->first();
                if (($vigente?->id) !== $anteriorEnviada) {
                    // Carrera con un reintento de la MISMA petición confirmado entre el paso 1 y este:
                    // se vuelve a consultar la clave (READ COMMITTED ve la fila ya confirmada).
                    if (($previa = $reintento()) !== null) {
                        return $previa;
                    }
                    throw new ConflictoRevision('concurrencia', 'Otra persona registró una revisión de este documento después de su consulta.',
                        ['vigente' => $vigente ? $this->publica($vigente) : null]);
                }

                // 4. Versión: se recalcula desde el archivo real y se compara con la consultada.
                $version = $this->documentos->version($bloqueada, $campo);
                if (!hash_equals($version->token(), (string) $datos['version'])) {
                    throw new ConflictoRevision('version', 'El archivo cambió desde que lo consultó. Vuelva a abrirlo antes de registrar la revisión.',
                        ['version' => $version->publica()]);
                }
                if ($version->ruta !== null && !$this->documentos->rutaSegura($version->ruta)) {
                    throw new ConflictoRevision('ruta', 'La ruta registrada del documento no es válida; requiere atención técnica.');
                }

                // 5. Reglas de negocio que dependen del estado real.
                if ($resultado === ResultadoRevision::Validado && !$version->cargado()) {
                    throw ValidationException::withMessages(['resultado' => 'No es posible validar un documento sin archivo disponible.']);
                }
                if ($vigente !== null && $vigente->resultado !== $resultado && $motivo === null) {
                    throw ValidationException::withMessages(['motivo_cambio' => 'Indique el motivo del cambio respecto del resultado anterior.']);
                }
                if ($vigente === null && $motivo !== null) {
                    $motivo = null; // sin revisión anterior no hay cambio que justificar
                }

                $revision = new Revision();
                $revision->forceFill([
                    'solicitud_id' => $bloqueada->id,
                    'documento_clave' => $campo,
                    'archivo_estado' => $version->estado,
                    'version_ruta' => $version->ruta,
                    'version_sha256' => $version->sha256,
                    'version_bytes' => $version->bytes,
                    'resultado' => $resultado,
                    'observaciones' => $observaciones,
                    'motivo_cambio' => $motivo,
                    'revision_anterior_id' => $vigente?->id,
                    'coordinador_id' => $coordinador->id,
                    'coordinador_nombre' => $coordinador->name,
                    'coordinador_rol' => $coordinador->rol,
                    'solicitud_estado' => $bloqueada->estado,
                    'clave_idempotencia' => $datos['clave_idempotencia'],
                ])->save();

                return [$revision->refresh(), true];
            });
        } catch (UniqueConstraintViolationException) {
            // Carrera entre dos registros simultáneos: la base impidió la bifurcación o el duplicado.
            $previa = Revision::query()->where('clave_idempotencia', $datos['clave_idempotencia'])->first();
            if ($previa !== null) {
                return $this->registrar($coordinador, $solicitud, $campo, $datos); // reintento idéntico o conflicto
            }
            throw new ConflictoRevision('concurrencia', 'Otra persona registró una revisión de este documento al mismo tiempo.');
        }
    }

    public function publica(Revision $r): array
    {
        return [
            'id' => $r->id,
            'resultado' => $r->resultado->value,
            'resultado_etiqueta' => $r->resultado->etiqueta(),
            'observaciones' => $r->observaciones,
            'motivo_cambio' => $r->motivo_cambio,
            'coordinador' => $r->coordinador_nombre,
            'coordinador_rol' => $r->coordinador_rol,
            'revisado_en' => $r->revisado_en?->timezone('America/Mexico_City')->format('d/m/Y H:i:s'),
            'revision_anterior_id' => $r->revision_anterior_id,
            'version' => $r->version()->publica(),
        ];
    }

    private function texto(?string $valor): ?string
    {
        $valor = $valor === null ? null : trim($valor);

        return $valor === '' ? null : $valor;
    }
}
