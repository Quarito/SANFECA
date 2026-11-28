<?php

namespace App\Services\Anfeca;

use App\Models\Cuenta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

/**
 * Reglas de antecedentes y refrendo sobre HISTORICO_CERTIFICADO y SOLICITUD:
 *  - El refrendo lo inicia explícitamente la persona capturando el número de su certificado.
 *  - El certificado debe existir para la certificación elegida y su CURP debe coincidir con la de la persona.
 *  - Una persona puede tener varios certificados: cada uno se valida por su propio folio.
 *  - Periodicidad: el siguiente refrendo procede a partir de (último año registrado + 3).
 *  - No se admite un segundo refrendo del mismo certificado mientras otro esté en curso o ya enviado en el periodo.
 */
final class ConsultaRefrendo
{
    public function __construct(private readonly HistoricoCertificados $historico)
    {
    }

    private function curp(int $usuario): ?string
    {
        $cuenta = Cuenta::find($usuario);
        if (!$cuenta || !$cuenta->datosAutorizados()) {
            return null;
        }
        $curp = DB::table('persona')->where('pers_id_persona', $cuenta->usua_id_persona)->value('pers_curp');

        return filled($curp) ? mb_strtoupper(trim($curp)) : null;
    }

    /** Certificado propio y válido para refrendo, o null (respuesta genérica: no revela datos de terceros). */
    public function validarCertificadoAnterior(int $usuario, string $certificacion, string $numero): ?Fluent
    {
        $numero = trim($numero);
        if (!preg_match('/^[0-9]{1,7}$/', $numero)) {
            return null;
        }
        $curp = $this->curp($usuario);
        $certificado = $curp ? $this->historico->certificado($numero, $certificacion) : null;
        if (!$certificado || $certificado->curps === [] || $certificado->curps !== [$curp]) {
            return null;
        }

        return trim((string) $certificado->primera_vez) === '' ? null : $certificado;
    }

    /** Antecedentes de la persona en la certificación (pantalla de selección). */
    public function consultar(int $usuario, ?string $certificacion): array
    {
        $resultado = ['estado' => 'sin_seleccion', 'total' => 0, 'verificados' => 0, 'posibles_coincidencias' => 0, 'historial' => []];
        if (!$certificacion) {
            return $resultado;
        }
        $curp = $this->curp($usuario);
        $certificados = $curp ? $this->historico->dePersona($curp, $certificacion) : [];
        $historial = array_map(fn (Fluent $c) => [
            'certificacion' => $c->certificacion,
            'primera_vez' => trim((string) $c->primera_vez),
            'refrendos' => $this->refrendos($c),
        ], $certificados);

        return [
            'estado' => $certificados ? 'verificado' : 'sin_vinculacion',
            'total' => count($certificados),
            'verificados' => count($certificados),
            'posibles_coincidencias' => 0,
            'historial' => $historial,
        ];
    }

    /** @return list<array{numero:int, anio:string}> */
    public function refrendos(Fluent $certificado): array
    {
        $lista = [];
        for ($i = 1; $i <= (int) config('anfeca_8088.refrendos_maximos', 7); $i++) {
            $anio = trim((string) $certificado->{'refrendo_'.$i});
            if ($anio !== '') {
                $lista[] = ['numero' => $i, 'anio' => $anio];
            }
        }

        return $lista;
    }

    /** Número del refrendo que corresponde (último registrado + 1). */
    public function refrendoCorrespondiente(SolicitudVista|Fluent $solicitud): ?int
    {
        $certificado = $solicitud->numero_certificado
            ? $this->historico->certificado((string) $solicitud->numero_certificado, $solicitud->certificacion) : null;
        if (!$certificado) {
            return null;
        }
        $numeros = array_column($this->refrendos($certificado), 'numero');

        return ($numeros ? max($numeros) : 0) + 1;
    }

    public function obtenerDatosRefrendo(Fluent $certificado): array
    {
        $numeros = array_column($this->refrendos($certificado), 'numero');

        return ['refrendo_actual' => ($numeros ? max($numeros) : 0) + 1, 'numero_refrendo_asignado' => null];
    }

    /** Vigencia por año (el histórico solo guarda años). */
    public function verificarVigenciaRefrendo(Fluent $certificado): array
    {
        $ultimoAnio = preg_match('/^[0-9]{4}$/', trim((string) $certificado->primera_vez)) ? (int) $certificado->primera_vez : null;
        $ultimoRefrendo = 0;
        foreach ($this->refrendos($certificado) as ['numero' => $n, 'anio' => $anio]) {
            if (!preg_match('/^[0-9]{4}$/', $anio)) {
                return ['elegible' => false, 'motivo' => 'fecha_historica_invalida'];
            }
            $ultimoAnio = max($ultimoAnio ?? 0, (int) $anio);
            $ultimoRefrendo = max($ultimoRefrendo, $n);
        }
        if ($ultimoAnio === null) {
            return ['elegible' => false, 'motivo' => 'sin_fecha_historica'];
        }
        $proximo = $ultimoAnio + (int) config('anfeca_8088.periodicidad_refrendo', 3);
        $elegible = (int) now(Tramites::ZONA)->year >= $proximo;

        return ['elegible' => $elegible, 'ultimo_anio' => $ultimoAnio, 'proximo_anio' => $proximo,
            'refrendo_correspondiente' => $ultimoRefrendo + 1, 'motivo' => $elegible ? 'vigencia_cumplida' : 'certificado_vigente'];
    }

    /**
     * ¿Ya existe otro refrendo de este certificado en el periodo vigente (en borrador o enviado) distinto de
     * $excepto? El periodo inicia en el año del último evento histórico.
     */
    public function refrendoDuplicado(Fluent $certificado, ?int $excepto = null): bool
    {
        $vigencia = $this->verificarVigenciaRefrendo($certificado);
        $desde = ($vigencia['ultimo_anio'] ?? 0).'-12-31';

        return DB::table('solicitud as s')
            ->join('convocatoria as c', 'c.conv_id_convocatoria', '=', 's.soli_id_convocatoria')
            ->join('tipo_convocatoria as t', 't.tico_id_tipo_convocatoria', '=', 'c.conv_id_tipo_convocatoria')
            ->where('t.tico_siglas', $certificado->certificacion)
            ->where('s.soli_numero_certificado', $certificado->numero_certificado)
            ->where('s.soli_fecha_registro_solicitud', '>', $desde)
            ->when($excepto, fn ($q) => $q->where('s.soli_id_solicitud', '<>', $excepto))
            ->exists();
    }

    /**
     * Primera vez: procede salvo que la persona ya tenga un certificado de la misma certificación Y la misma área
     * (en ese caso debe tramitar el refrendo de ese certificado). Otra área = otro certificado distinto.
     */
    public function primeraVezPermitida(int $usuario, string $certificacion, string $area): bool
    {
        $curp = $this->curp($usuario);
        if (!$curp) {
            return false;
        }
        foreach ($this->historico->dePersona($curp, $certificacion) as $c) {
            if (Catalogo::mismaArea((string) $c->area_certificacion, $area)) {
                return false;
            }
        }

        return true;
    }
}
