<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acuse de recepción - {{ $datos['folio'] }}</title>
    <style>
        @page { margin: 30px 38px 38px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #292929; line-height: 1.4; }
        .encabezado { width: 100%; border-bottom: 3px solid #8d2226; padding-bottom: 10px; }
        .encabezado td { vertical-align: middle; }
        .logo { width: 95px; }
        .institucional { text-align: right; font-size: 9px; }
        h1 { font-size: 18px; color: #8d2226; margin: 18px 0 4px; }
        .subtitulo { color: #555; margin: 0 0 12px; }
        h2 { font-size: 10px; color: #8d2226; background: #f5eeee; padding: 6px 8px; margin: 12px 0 4px; }
        table.datos { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .datos td { width: 50%; padding: 5px 8px; vertical-align: top; border-bottom: 1px solid #e9e9e9; overflow-wrap: anywhere; }
        .etiqueta { display: block; color: #656565; font-size: 8px; margin-bottom: 2px; }
        .valor { overflow-wrap: anywhere; word-wrap: break-word; }
        .aviso { border-left: 3px solid #8d2226; background: #faf7f7; padding: 9px 12px; margin-top: 14px; }
        .nota { color: #555; font-size: 8px; }
        .bloque { page-break-inside: avoid; }
        footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 7px; color: #666; text-align: right; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td><img class="logo" src="{{ $logo ?? '' }}" alt="ANFECA"></td>
            <td class="institucional">
                <strong>ASOCIACIÓN NACIONAL DE FACULTADES Y ESCUELAS<br>DE CONTADURÍA Y ADMINISTRACIÓN</strong><br>
                Coordinación Nacional de Certificación Académica
            </td>
        </tr>
    </table>
    <h1>Acuse de recepción de solicitud</h1>
    <p class="subtitulo">Comprobante de recepción en el sistema de certificación académica</p>
    <table class="datos">
        <tr>
            <td><span class="etiqueta">Folio de solicitud</span><strong>{{ $datos['folio'] }}</strong></td>
            <td><span class="etiqueta">Fecha de recepción</span>{{ $datos['fecha_recepcion'] }}<br><span class="nota">Hora de Ciudad de México</span></td>
        </tr>
        <tr>
            <td><span class="etiqueta">Certificación solicitada</span>{{ $datos['certificacion'] }}</td>
            <td><span class="etiqueta">Tipo de trámite</span>{{ $datos['tipo_tramite'] === 'refrendo' ? 'Refrendo' : 'Primera vez' }}</td>
        </tr>
        <tr>
            <td><span class="etiqueta">Personal académico</span>{{ $datos['tipo_personal'] }}</td>
            <td><span class="etiqueta">Área de certificación</span><span class="valor">{{ $datos['area_certificacion'] }}</span></td>
        </tr>
    </table>
    <div class="bloque">
        <h2>Datos del solicitante</h2>
        <table class="datos">
            <tr><td colspan="2"><span class="etiqueta">Nombre completo</span><span class="valor">{{ trim(implode(' ', array_filter([$datos['persona']['nombres'], $datos['persona']['apellido_paterno'], $datos['persona']['apellido_materno'] ?? null]))) }}</span></td></tr>
            <tr>
                <td><span class="etiqueta">CURP</span>{{ $datos['persona']['curp'] ?? 'No registrada' }}</td>
                <td><span class="etiqueta">Correo electrónico</span><span class="valor">{{ $datos['persona']['correo_electronico'] }}</span></td>
            </tr>
            <tr>
                <td><span class="etiqueta">Teléfono particular</span>{{ $datos['persona']['telefono_particular'] ?: 'No registrado' }}</td>
                <td><span class="etiqueta">Teléfono celular</span>{{ $datos['persona']['telefono_celular'] ?: 'No registrado' }}</td>
            </tr>
        </table>
    </div>
    @if($datos['refrendo'])
        <div class="bloque">
            <h2>Información del refrendo</h2>
            <table class="datos">
                <tr>
                    <td><span class="etiqueta">Certificado anterior</span>{{ $datos['refrendo']['numero_certificado'] ?: 'No vinculado en el sistema' }}</td>
                    <td><span class="etiqueta">Número de refrendo</span>{{ $datos['refrendo']['refrendo_actual'] ?? 'No registrado' }}</td>
                </tr>
                <tr><td colspan="2"><span class="etiqueta">Modalidad</span>{{ $datos['refrendo']['aplica_articulo_14'] ? 'Artículo 14 · Inciso '.$datos['refrendo']['inciso_articulo_14'] : 'Refrendo ordinario' }}</td></tr>
            </table>
        </div>
    @endif
    <div class="bloque">
        <h2>Formación académica</h2>
        @if($datos['omite_formacion'])
            <p>No se requiere volver a presentar documentación académica para la modalidad de artículo 14 registrada.</p>
        @elseif($datos['formacion'])
            <table class="datos">
                <tr>
                    <td><span class="etiqueta">Formación de origen · {{ $datos['formacion']['nivel_origen'] }}</span><span class="valor">{{ $datos['formacion']['titulo_origen'] }}</span></td>
                    <td><span class="etiqueta">Último grado · {{ $datos['formacion']['nivel_ultimo_grado'] }}</span><span class="valor">{{ $datos['formacion']['titulo_ultimo_grado'] }}</span></td>
                </tr>
            </table>
        @else
            <p>No se registró formación académica en esta solicitud.</p>
        @endif
    </div>
    <div class="bloque">
        <h2>Institución de adscripción</h2>
        @if($datos['institucion'])
            <table class="datos">
                <tr><td colspan="2"><span class="etiqueta">Institución</span><span class="valor">{{ $datos['institucion']['nombre'] }}</span></td></tr>
                <tr>
                    <td><span class="etiqueta">Número de afiliación</span>{{ $datos['institucion']['numero_afiliacion'] ?: 'No registrado' }}</td>
                    <td><span class="etiqueta">Zona regional</span>{{ $datos['institucion']['zona_anfeca'] ?: 'No registrada' }}</td>
                </tr>
                <tr><td colspan="2"><span class="etiqueta">Campus / Unidad / Sede</span><span class="valor">{{ $datos['institucion']['sede'] ?: 'No registrada' }}</span></td></tr>
            </table>
        @else
            <p>No se capturó una institución de adscripción en esta solicitud.</p>
        @endif
    </div>
    <div class="aviso bloque">
        <strong>Alcance de este acuse</strong><br>
        Este documento acredita únicamente la recepción de la solicitud en el sistema.
        Al momento del envío, la solicitud queda pendiente de revisión.
        No implica aprobación, validación de los documentos ni otorgamiento de la certificación.
    </div>
    <p class="nota">La información corresponde al momento del envío. Conserve el folio para consultar su solicitud.</p>
    <footer>ANFECA · Folio {{ $datos['folio'] }} · Formato de acuse v1</footer>
</body>
</html>
