<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 35px 42px; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 9px;
        color: #222;
    }

    h1, h2, h3 { text-align: center; }

    h1 { font-size: 12px; }
    h2 { font-size: 11px; margin-top: 15px; }
    h3 { font-size: 10px; margin-top: 12px; }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }

    td {
        padding: 4px;
        border-bottom: 1px solid #ddd;
        vertical-align: top;
    }

    .etiqueta { font-weight: bold; width: 35%; }

    .nota {
        margin-top: 16px;
        font-size: 8px;
        text-align: justify;
    }
</style>
</head>
<body>

<h1>ASOCIACIÓN NACIONAL DE FACULTADES Y ESCUELAS DE CONTADURÍA Y ADMINISTRACIÓN</h1>
<h2>Coordinación Nacional de Certificación Académica</h2>
<h2>Acuse de recepción de solicitud</h2>

<table>
<tr><td class="etiqueta">Folio</td><td>{{ $solicitud->id }}</td></tr>
<tr><td class="etiqueta">Fecha de recepción</td><td>{{ $fechaRecepcion }}</td></tr>
<tr><td class="etiqueta">Certificación</td><td>{{ $solicitud->certificacion }}</td></tr>
<tr><td class="etiqueta">Tipo de trámite</td><td>{{ $solicitud->tipo_tramite === 'refrendo' ? 'Refrendo' : 'Certificación inicial' }}</td></tr>
<tr><td class="etiqueta">Tipo de personal</td><td>{{ $solicitud->tipo_personal ?? 'No registrado' }}</td></tr>
<tr><td class="etiqueta">Área de certificación</td><td>{{ $solicitud->area_certificacion ?? 'No registrada' }}</td></tr>
</table>

<h3>Datos del solicitante</h3>
<table>
<tr><td class="etiqueta">Nombre completo</td><td>{{ trim(($datos?->nombres ?? '') . ' ' . ($datos?->apellido_paterno ?? '') . ' ' . ($datos?->apellido_materno ?? '')) }}</td></tr>
<tr><td class="etiqueta">CURP</td><td>{{ $datos?->curp ?? '' }}</td></tr>
<tr><td class="etiqueta">Correo electrónico</td><td>{{ $datos?->correo_electronico ?? '' }}</td></tr>
<tr><td class="etiqueta">Teléfono particular</td><td>{{ $datos?->telefono_particular ?? '' }}</td></tr>
<tr><td class="etiqueta">Teléfono celular</td><td>{{ $datos?->telefono_celular ?? '' }}</td></tr>
</table>

<h3>Formación académica</h3>
<table>
<tr><td class="etiqueta">Nivel de origen</td><td>{{ $formacion?->nivel_origen ?? '' }}</td></tr>
<tr><td class="etiqueta">Título de origen</td><td>{{ $formacion?->titulo_origen ?? '' }}</td></tr>
<tr><td class="etiqueta">Último grado</td><td>{{ $formacion?->nivel_ultimo_grado ?? '' }}</td></tr>
<tr><td class="etiqueta">Título del último grado</td><td>{{ $formacion?->titulo_ultimo_grado ?? '' }}</td></tr>
</table>

<h3>Institución afiliada</h3>
<table>
<tr><td class="etiqueta">Institución</td><td>{{ $institucion?->nombre ?? '' }}</td></tr>
<tr><td class="etiqueta">Número de afiliación</td><td>{{ $institucion?->numero_afiliacion ?? '' }}</td></tr>
<tr><td class="etiqueta">Zona ANFECA</td><td>{{ $institucion?->zona_anfeca ?? '' }}</td></tr>
<tr><td class="etiqueta">Sede</td><td>{{ $institucion?->sede ?? '' }}</td></tr>
</table>

<h3>Documentos recibidos</h3>
<table>
@foreach($documentosRecibidos as $documento)
<tr>
    <td>{{ $documento }}</td>
    <td style="width: 20%;">Recibido</td>
</tr>
@endforeach
</table>

<p class="nota">
Este documento acredita únicamente la recepción electrónica de la solicitud
y de los archivos registrados en el sistema ANFECA. No constituye aprobación,
dictamen favorable ni otorgamiento de certificación.
</p>

</body>
</html>
