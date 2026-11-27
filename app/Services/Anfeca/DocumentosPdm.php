<?php

namespace App\Services\Anfeca;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Fluent;
use RuntimeException;

/**
 * Documentos de una solicitud sobre SOLICITUD_DOCUMENTO, DOCUMENTO, ESTADO_SOLICITUD_DOCUMENTO,
 * ESTADO_DOCUMENTO y PAGO.
 *
 *  - Cada carga crea una fila SOLICITUD_DOCUMENTO (fecha y hora) y su estado inicial «Cargado».
 *  - Una nueva versión del mismo documento marca la anterior como «Sustituido»: el historial se conserva.
 *  - El archivo se guarda en {carpeta}/{SOLI_ID}/{SOCU_ID}.{ext}: su ubicación se deriva de la PK de
 *    SOLICITUD_DOCUMENTO (el modelo no tiene columna de ruta), así que la asociación vive en la base.
 *  - El comprobante de pago también se registra en PAGO.PAGO_URL_COMPROBANTE.
 *  - El enlace de evidencias se registra como documento «Enlace de evidencias»; la URL queda en la
 *    observación de su estado inicial (ESDO_OBSERVACION, 300 caracteres).
 */
final class DocumentosPdm
{
    public const OBSERVACION_CARGA = 'Documento cargado por la persona solicitante.';
    public const OBSERVACION_SUSTITUCION = 'Sustituido por una versión posterior del mismo documento.';

    public function disco(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('anfeca_8088.disco', 'local'));
    }

    private function carpeta(int $solicitud): string
    {
        return trim((string) config('anfeca_8088.carpeta'), '/').'/'.$solicitud;
    }

    /** Ruta del archivo de una fila SOLICITUD_DOCUMENTO (o null si no existe físicamente). */
    public function rutaDe(int $solicitud, int $socu): ?string
    {
        foreach ($this->disco()->files($this->carpeta($solicitud)) as $ruta) {
            if (preg_match('#/'.$socu.'\.[a-z0-9]+$#', $ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    /** Guarda una nueva versión de un documento. Debe llamarse dentro de una transacción. @return string ruta */
    public function guardar(int $solicitud, string $campo, UploadedFile $archivo): string
    {
        $docu = Catalogo::documento($campo);
        $ahora = now('America/Mexico_City');
        $this->sustituirVigente($solicitud, $docu, $ahora);
        $socu = DB::table('solicitud_documento')->insertGetId([
            'socu_id_solicitud' => $solicitud,
            'socu_id_documento' => $docu,
            'socu_fecha' => $ahora->toDateString(),
            'socu_hora' => $ahora->format('H:i:s'),
        ], 'socu_id_documentacion');
        $this->estado($socu, 'CARGADO', self::OBSERVACION_CARGA, $ahora);

        $extension = strtolower($archivo->guessExtension() ?: $archivo->getClientOriginalExtension() ?: 'bin');
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $ruta = $this->disco()->putFileAs($this->carpeta($solicitud), $archivo, $socu.'.'.$extension);
        if ($ruta === false) {
            throw new RuntimeException('No fue posible guardar el archivo del documento.');
        }
        if ($campo === 'comprobante_pago_archivo') {
            if (mb_strlen($ruta) > 255) {
                throw new RuntimeException('La ruta del comprobante excede PAGO_URL_COMPROBANTE.');
            }
            DB::table('pago')->insert(['pago_id_solicitud' => $solicitud, 'pago_url_comprobante' => $ruta]);
        }

        return $ruta;
    }

    /** Enlace de evidencias (URL ≤ 300). */
    public function guardarEnlace(int $solicitud, string $url): void
    {
        $docu = Catalogo::documento('link');
        $ahora = now('America/Mexico_City');
        $this->sustituirVigente($solicitud, $docu, $ahora);
        $socu = DB::table('solicitud_documento')->insertGetId([
            'socu_id_solicitud' => $solicitud,
            'socu_id_documento' => $docu,
            'socu_fecha' => $ahora->toDateString(),
            'socu_hora' => $ahora->format('H:i:s'),
        ], 'socu_id_documentacion');
        $this->estado($socu, 'CARGADO', $url, $ahora);
    }

    public function estado(int $socu, string $clave, string $observacion, ?Carbon $momento = null): void
    {
        $momento ??= now('America/Mexico_City');
        DB::table('estado_solicitud_documento')->insert([
            'esdo_id_documentacion' => $socu,
            'esdo_id_estado_documento' => Catalogo::estadoDocumento($clave),
            'esdo_observacion' => mb_substr($observacion, 0, 300),
            'esdo_fecha' => $momento->toDateString(),
            'esdo_hora' => $momento->format('H:i:s'),
        ]);
    }

    private function sustituirVigente(int $solicitud, int $docu, Carbon $ahora): void
    {
        foreach ($this->vigentes($solicitud) as $v) {
            if ($v->docu === $docu) {
                $this->estado($v->socu, 'SUSTITUIDO', self::OBSERVACION_SUSTITUCION, $ahora);
            }
        }
    }

    /**
     * Versiones vigentes (cuyo último estado no es «Sustituido»), una por documento.
     *
     * @return array<string, object{socu:int, docu:int, campo:string, fecha:string, hora:string, estado:?string, observacion:?string}>
     */
    public function vigentes(int $solicitud): array
    {
        $campos = Catalogo::documentosPorId();
        $estados = Catalogo::estadosDocumentoPorId();
        $filas = DB::table('solicitud_documento as s')
            ->where('s.socu_id_solicitud', $solicitud)
            ->select('s.socu_id_documentacion as socu', 's.socu_id_documento as docu', 's.socu_fecha as fecha', 's.socu_hora as hora')
            ->selectSub(DB::table('estado_solicitud_documento as e')->whereColumn('e.esdo_id_documentacion', 's.socu_id_documentacion')
                ->orderByDesc('e.esdo_id_estado_solicitud_docume')->limit(1)->select('e.esdo_id_estado_documento'), 'estado')
            ->selectSub(DB::table('estado_solicitud_documento as e')->whereColumn('e.esdo_id_documentacion', 's.socu_id_documentacion')
                ->orderBy('e.esdo_id_estado_solicitud_docume')->limit(1)->select('e.esdo_observacion'), 'observacion_inicial')
            ->orderBy('s.socu_id_documentacion')->get();
        $vigentes = [];
        foreach ($filas as $f) {
            $clave = $estados[(int) $f->estado] ?? null;
            if ($clave === 'SUSTITUIDO' || !isset($campos[(int) $f->docu])) {
                continue;
            }
            $vigentes[$campos[(int) $f->docu]] = (object) [
                'socu' => (int) $f->socu, 'docu' => (int) $f->docu, 'campo' => $campos[(int) $f->docu],
                'fecha' => $f->fecha, 'hora' => $f->hora, 'estado' => $clave, 'observacion' => $f->observacion_inicial,
            ];
        }

        return $vigentes;
    }

    /** Ruta vigente de un campo, o null. */
    public function ruta(int $solicitud, string $campo): ?string
    {
        $v = $this->vigentes($solicitud)[$campo] ?? null;

        return $v ? $this->rutaDe($solicitud, $v->socu) : null;
    }

    public const PROBATORIOS = ['constancia_antiguedad_archivo', 'comprobante_pago_archivo', 'solicitud_articulo_14_archivo', 'constancia_articulo_14_archivo'];

    /** Documentos probatorios con los nombres de campo de las pantallas, o null si aún no se guardaron. */
    public function probatorios(int $solicitud): ?Fluent
    {
        $vigentes = $this->vigentes($solicitud);
        $datos = ['solicitud_id' => $solicitud, 'link' => isset($vigentes['link']) ? $vigentes['link']->observacion : null];
        $alguno = isset($vigentes['link']);
        foreach (self::PROBATORIOS as $campo) {
            $datos[$campo] = isset($vigentes[$campo]) ? $this->rutaDe($solicitud, $vigentes[$campo]->socu) : null;
            $alguno = $alguno || isset($vigentes[$campo]);
        }

        return $alguno ? new Fluent($datos) : null;
    }
}
