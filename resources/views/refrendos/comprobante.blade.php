<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Comprobante de refrendo</title><style>body{font:16px Arial;max-width:800px;margin:50px auto;padding:20px}h1{color:#950e16}dt{font-weight:bold;margin-top:15px}dd{margin:4px 0}@media print{button{display:none}}</style></head><body>
<h1>ANFECA — Comprobante de registro</h1><p>Solicitud de refrendo #{{ $solicitud->id }} · Ciclo {{ $solicitud->ciclo }}</p><dl>
<dt>Solicitante</dt><dd>{{ $solicitud->datos_personales['nombres'] ?? '' }} {{ $solicitud->datos_personales['apellido_paterno'] ?? '' }} {{ $solicitud->datos_personales['apellido_materno'] ?? '' }}</dd>
<dt>No. de certificado</dt><dd>{{ $solicitud->numero_certificado }}</dd><dt>Refrendo actual</dt><dd>{{ $solicitud->numero_refrendo }}</dd>
<dt>Modalidad</dt><dd>{{ $solicitud->articulo_14 ? 'Artículo '.$solicitud->inciso : 'Ordinario' }}</dd>
@foreach($solicitud->seleccion as $campo=>$valor)<dt>{{ ucwords(str_replace('_',' ', $campo)) }}</dt><dd>{{ $valor }}</dd>@endforeach
<dt>Institución</dt><dd>{{ $solicitud->formacion['institucion'] ?? '' }}</dd><dt>Fecha de registro</dt><dd>{{ $solicitud->completado_at->format('d/m/Y H:i') }}</dd></dl>
<p>Este comprobante acredita la recepción del registro, no la aprobación de la certificación.</p><button onclick="window.print()">Imprimir / Guardar como PDF</button></body></html>
