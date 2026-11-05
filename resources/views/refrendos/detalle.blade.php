@extends('refrendos.layout')
@section('contenido')
@if($antecedentes->isEmpty())
<div class="mensaje"><h2>Antecedente pendiente de verificación</h2><p>No hay un certificado histórico verificado vinculado a tu cuenta para la certificación elegida. Solicita a la coordinación que revise tu vinculación antes de continuar.</p><a class="boton" href="{{ route('refrendos.certificacion') }}">Anterior</a></div>
@else
<form class="formulario" method="POST" action="{{ route('refrendos.detalle.guardar') }}">@csrf
<div class="centro"><h2>Certificación</h2>
<label for="antecedente">No. de Refrendo Asignado</label>
<select id="antecedente" name="certificado_historico_id" required>
@foreach($antecedentes as $antecedente)
@php($numero = 1)
@for($i=1;$i<=7;$i++) @if(trim((string)$antecedente->{'refrendo_'.$i}) !== '') @php($numero = $i+1) @endif @endfor
<option value="{{ $antecedente->id }}" data-numero="{{ $numero }}" @selected((string)old('certificado_historico_id') === (string)$antecedente->id)>{{ $antecedente->numero_certificado }}</option>
@endforeach
</select>
<label for="numero">Refrendo actual</label><output id="numero"></output>
<fieldset><legend>¿Corresponde a Refrendo por Art. 14?</legend><div class="radios">
<label>Sí <input type="radio" name="articulo_14" value="1" @checked(old('articulo_14') === '1') required></label><label>No <input type="radio" name="articulo_14" value="0" @checked(old('articulo_14') === '0') required></label></div></fieldset>
<div id="inciso-campo"><label for="inciso">Inciso Art. 14</label><select name="inciso" id="inciso"><option value="">Seleccione una opción</option>@foreach($incisos as $valor=>$nombre)<option value="{{ $valor }}" @selected(old('inciso') === $valor)>{{ $valor }} — {{ $nombre }}</option>@endforeach</select></div>
<p class="ayuda" id="ordinario" hidden>Usted no está por Art. 14</p>
</div><div class="botones"><a class="boton" href="{{ route('refrendos.certificacion') }}">Anterior</a><button class="boton">Siguiente</button></div>
</form>
<script>
const antecedente = document.getElementById('antecedente');
function actualizar(){document.getElementById('numero').textContent='Refrendo número '+antecedente.selectedOptions[0].dataset.numero;
const tipo=document.querySelector('[name=articulo_14]:checked')?.value;
document.getElementById('inciso-campo').hidden=tipo!=='1';document.getElementById('inciso').disabled=tipo!=='1';document.getElementById('inciso').required=tipo==='1';document.getElementById('ordinario').hidden=tipo!=='0';}
document.querySelectorAll('select,input').forEach(el=>el.addEventListener('change',actualizar));actualizar();
</script>
@endif
@endsection
