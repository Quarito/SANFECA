<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page {
    margin: 34px 38px 40px 38px;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 8px;
    color: #253044;
}

.encabezado {
    text-align: right;
    font-size: 9px;
    font-weight: bold;
    line-height: 1.5;
    color: #263b55;
    margin-bottom: 24px;
}

.titulo {
    font-size: 17px;
    font-weight: bold;
    margin-bottom: 5px;
}

.subtitulo {
    font-size: 9px;
    color: #687588;
    margin-bottom: 18px;
}

.seccion {
    font-size: 10px;
    font-weight: bold;
    color: #263b55;
    border-bottom: 1px solid #cbd5e1;
    padding-bottom: 5px;
    margin-top: 14px;
    margin-bottom: 9px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

td {
    width: 50%;
    padding: 5px 10px 7px 0;
    vertical-align: top;
}

.etiqueta {
    font-size: 7px;
    color: #64748b;
    margin-bottom: 4px;
}

.valor {
    font-size: 9px;
    font-weight: bold;
    overflow-wrap: break-word;
}

.documentos td {
    width: 33%;
    font-size: 8px;
    padding: 5px 4px;
}

.marca {
    font-weight: bold;
    color: #1e4873;
}

.nota {
    font-size: 7px;
    color: #64748b;
    margin-top: 9px;
}

.aviso {
    background-color: #f1f5f9;
    padding: 11px;
    margin-top: 16px;
    font-size: 8px;
    line-height: 1.6;
}

.pie {
    text-align: right;
    font-size: 7px;
    color: #64748b;
    margin-top: 22px;
}

.logo-institucional {
    width: 115px;
    height: auto;
}

.tabla-encabezado {
    width: 100%;
    margin-bottom: 18px;
}

.tabla-encabezado td {
    border: none;
    vertical-align: middle;
}

.tabla-encabezado .celda-logo {
    width: 28%;
    text-align: left;
}

.tabla-encabezado .celda-institucion {
    width: 72%;
    text-align: right;
    color: #263b55;
    font-size: 9px;
    font-weight: bold;
    line-height: 1.5;
}

.titulo {
    color: #263b55;
}

.seccion {
    color: #263b55;
    border-bottom: 1px solid #9fb4c9;
}

.aviso {
    background-color: #edf3f8;
    border-left: 3px solid #263b55;
}


/* ANFECA_ACUSE_COMPACTO_V2 */

@page {
    size: letter portrait;
    margin: 22px 34px 25px 34px;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 7.5px;
    color: #414141;
    line-height: 1.25;
}

.logo-institucional {
    width: 95px;
    height: auto;
}

.tabla-encabezado {
    margin-bottom: 8px;
}

.tabla-encabezado td {
    padding: 0 5px 2px 0;
    vertical-align: middle;
}

.tabla-encabezado .celda-logo {
    width: 23%;
}

.tabla-encabezado .celda-institucion {
    width: 77%;
    color: #555555;
    font-size: 8px;
    line-height: 1.3;
}

.titulo {
    color: #D60000;
    font-size: 15px;
    margin-bottom: 3px;
}

.subtitulo {
    color: #777777;
    font-size: 8px;
    margin-bottom: 9px;
}

.seccion {
    color: #D60000;
    font-size: 9px;
    margin-top: 8px;
    margin-bottom: 5px;
    padding-bottom: 3px;
    border-bottom: 1px solid #999999;
}

table {
    margin-bottom: 4px;
}

td {
    padding: 3px 8px 4px 0;
}

.etiqueta {
    color: #777777;
    font-size: 7px;
    margin-bottom: 2px;
}

.valor {
    color: #333333;
    font-size: 8px;
}

.documentos td {
    font-size: 7px;
    padding: 3px;
}

.marca {
    color: #D60000;
}

.nota {
    font-size: 7px;
    margin-top: 5px;
}

.aviso {
    background-color: #F3F3F3;
    border-left: 3px solid #D60000;
    padding: 7px 9px;
    margin-top: 8px;
    font-size: 7px;
    line-height: 1.35;
}

.pie {
    color: #777777;
    margin-top: 9px;
    font-size: 7px;
}


/* ANFECA_ACUSE_LETRA_V3 */

body {
    font-size: 8.5px;
}

.titulo {
    font-size: 16px;
}

.subtitulo {
    font-size: 9px;
}

.seccion {
    font-size: 10px;
}

.etiqueta {
    font-size: 8px;
}

.valor {
    font-size: 9px;
}

.documentos td {
    font-size: 8px;
}

.aviso {
    font-size: 7.5px;
}

.nota,
.pie {
    font-size: 7.5px;
}

.tabla-encabezado .celda-institucion {
    font-size: 8.5px;
}

</style>
</head>

<body>

<table class="tabla-encabezado">
<tr>
    <td class="celda-logo">
        <img
            src="{{ public_path('images/certificacion_anfeca.png') }}"
            class="logo-institucional"
            alt="ANFECA"
        >
    </td>
    <td class="celda-institucion">
        ASOCIACIÓN NACIONAL DE FACULTADES Y ESCUELAS<br>
        DE CONTADURÍA Y ADMINISTRACIÓN<br>
        Coordinación Nacional de Certificación Académica
    </td>
</tr>
</table>

<div class="titulo">Acuse de recepción de solicitud</div>
<div class="subtitulo">
    Comprobante de recepción en el sistema de certificación académica
</div>

<table>
<tr>
<td>
    <div class="etiqueta">Folio de solicitud</div>
    <div class="valor">{{ $solicitud->id }}</div>
</td>
<td>
    <div class="etiqueta">Fecha de recepción</div>
    <div class="valor">{{ $fechaRecepcion }}</div>
    <div class="etiqueta">Hora de Ciudad de México</div>
</td>
</tr>
<tr>
<td>
    <div class="etiqueta">Certificación solicitada</div>
    <div class="valor">{{ $solicitud->certificacion }}</div>
</td>
<td>
    <div class="etiqueta">Tipo de trámite</div>
    <div class="valor">
        @php
    $refrendo = $solicitud->datosRefrendo;

    $articulosValidos = [
        '13.1', '14', '14.1', '14.2', '14.3', '14.4'
    ];

    $inciso = trim((string) ($refrendo?->inciso_articulo_14 ?? ''));

    $esRefrendoArticulo =
        $solicitud->tipo_tramite === 'refrendo'
        && $refrendo !== null
        && (int) $refrendo->aplica_articulo_14 === 1
        && in_array($inciso, $articulosValidos, true);

    if ($solicitud->tipo_tramite === 'inicial') {
        $tipoTramiteAcuse = 'Primera vez';
    } elseif ($esRefrendoArticulo) {
        $tipoTramiteAcuse = 'Refrendo por artículo ' . $inciso;
    } else {
        $tipoTramiteAcuse = 'Refrendo';
    }
@endphp

{{ $tipoTramiteAcuse }}
    </div>
</td>
</tr>
<tr>
<td>
    <div class="etiqueta">Personal académico</div>
    <div class="valor">{{ $solicitud->tipo_personal ?? 'No registrado' }}</div>
</td>
<td>
    <div class="etiqueta">Área de certificación</div>
    <div class="valor">{{ $solicitud->area_certificacion ?? 'No registrada' }}</div>
</td>
</tr>
</table>

<div class="seccion">Datos del solicitante</div>

<table>
<tr>
<td colspan="2">
    <div class="etiqueta">Nombre completo</div>
    <div class="valor">
        {{ trim(($datos?->nombres ?? '') . ' ' . ($datos?->apellido_paterno ?? '') . ' ' . ($datos?->apellido_materno ?? '')) }}
    </div>
</td>
</tr>
<tr>
<td>
    <div class="etiqueta">CURP</div>
    <div class="valor">{{ $datos?->curp ?? 'No registrada' }}</div>
</td>
<td>
    <div class="etiqueta">Correo electrónico</div>
    <div class="valor">{{ $datos?->correo_electronico ?? 'No registrado' }}</div>
</td>
</tr>
<tr>
<td>
    <div class="etiqueta">Teléfono particular</div>
    <div class="valor">{{ $datos?->telefono_particular ?: 'No registrado' }}</div>
</td>
<td>
    <div class="etiqueta">Teléfono celular</div>
    <div class="valor">{{ $datos?->telefono_celular ?: 'No registrado' }}</div>
</td>
</tr>
</table>

<div class="seccion">Formación académica</div>

<table>
<tr>
<td>
    <div class="etiqueta">
        Formación de origen · {{ $formacion?->nivel_origen ?? 'No registrada' }}
    </div>
    @if(
        $formacion?->titulo_origen === 'Otro'
        && filled($formacion?->titulo_origen_otro)
    )
        <div class="valor">{{ $formacion->titulo_origen_otro }}</div>
        <div class="etiqueta">Otro (título especificado)</div>
    @else
        <div class="valor">{{ $formacion?->titulo_origen ?? 'No registrado' }}</div>
    @endif
</td>
<td>
    <div class="etiqueta">
        Último grado · {{ $formacion?->nivel_ultimo_grado ?? 'No registrado' }}
    </div>
    @if(
        $formacion?->titulo_ultimo_grado === 'Otro'
        && filled($formacion?->titulo_ultimo_grado_otro)
    )
        <div class="valor">{{ $formacion->titulo_ultimo_grado_otro }}</div>
        <div class="etiqueta">Otro (título especificado)</div>
    @else
        <div class="valor">{{ $formacion?->titulo_ultimo_grado ?? 'No registrado' }}</div>
    @endif
</td>
</tr>
</table>

<div class="seccion">Institución de procedencia</div>

<table>
<tr>
    <td>
        <div class="etiqueta">Número de afiliación</div>
        <div class="valor">{{ $institucion?->numero_afiliacion ?? 'No registrado' }}</div>
    </td>
    <td>
        <div class="etiqueta">Zona regional</div>
        <div class="valor">{{ $institucion?->zona_anfeca ?? 'No registrada' }}</div>
    </td>
</tr>
<tr>
    <td>
        <div class="etiqueta">Institución</div>
        <div class="valor">{{ $institucion?->nombre ?? 'No registrada' }}</div>
    </td>
    <td>
        <div class="etiqueta">Facultad</div>
        <div class="valor">No registrada</div>
    </td>
</tr>
<tr>
    <td>
        <div class="etiqueta">Campus</div>
        <div class="valor">{{ $institucion?->campus ?: 'No registrado' }}</div>
    </td>
    <td>
        <div class="etiqueta">Sede</div>
        <div class="valor">{{ $institucion?->sede ?: 'No registrada' }}</div>
    </td>
</tr>
</table>

<div class="seccion">Documentos adjuntados al enviar la solicitud</div>

@php
    // Orden institucional fijo de presentación del acuse.
    $ordenDocumentos = [
        [
            'Título de origen',
            'Título del último grado',
            'Constancia de antigüedad',
        ],
        [
            'Cédula de origen',
            'Cédula del último grado',
            'Comprobante de pago',
        ],
    ];

    // Los documentos del artículo 14 se agregan al final.
    // Solo se muestran cuando corresponden al expediente.
    if ($esRefrendoArticulo) {
        $ordenDocumentos[] = [
            'Solicitud artículo 14',
            'Constancia artículo 14',
            null,
        ];
    }
@endphp

<table class="documentos">
@foreach($ordenDocumentos as $fila)
<tr>
    @foreach($fila as $documento)
    <td>
        @if($documento !== null && in_array($documento, $documentosRecibidos, true))
            <span class="marca">X</span> {{ $documento }}
        @endif
    </td>
    @endforeach
</tr>
@endforeach
</table>

<div class="nota">
    Casilla marcada: archivo disponible en el expediente al generar el acuse.
    No representa validación de su contenido.
</div>

{{-- COMPROMISO_ACUSE_ANFECA_20261119 --}}
@if($solicitud->compromiso_aceptado ?? false)
<div class="seccion">
    Compromiso de entrega de documentación
</div>

<div class="aviso">
    Para completar su solicitud, deberá entregar las fotografías
    indicadas en la convocatoria y el comprobante de pago original.

    <div style="margin-top: 5px;">
        <strong>Compromiso aceptado por el solicitante.</strong>
    </div>
</div>
@endif

<div class="aviso">
    <strong>Alcance de este acuse</strong><br>
    Este documento acredita únicamente la recepción de la solicitud en el
    sistema. Al momento del envío, la solicitud queda pendiente de revisión.
    No implica aprobación, validación de los documentos ni otorgamiento
    de la certificación.
</div>

<p class="nota">
    Conserve el folio para consultar su solicitud.
</p>

<div class="pie">
    ANFECA · Folio {{ $solicitud->id }} · Formato de acuse v1
</div>

</body>
</html>
