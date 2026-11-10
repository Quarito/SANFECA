@php
    $esPreview = request()->is('preview/*');
    $tieneDatos = false;
    $esRefrendo = false;

    if (!$esPreview && auth()->check()) {
        $tieneDatos = \App\Models\DatosSolicitante::where(
            'user_id', auth()->id()
        )->exists();

        $solicitudMenu = \App\Models\SolicitudCertificacion::where(
            'user_id', auth()->id()
        )
            ->where('estado', 'borrador')
            ->latest('id')
            ->first();

        $esRefrendo = $solicitudMenu?->tipo_tramite === 'refrendo';
    }

    $etapas = [
        [
            'nombre' => 'Datos personales',
            'descripcion' => 'Información del solicitante',
            'ruta' => $tieneDatos ? 'mis-datos.edit' : 'registro.datos',
            'preview' => 'preview.datos',
        ],
        [
            'nombre' => 'Certificación',
            'descripcion' => 'Selección de certificación',
            'ruta' => 'registro.certificacion',
            'preview' => 'preview.certificacion',
        ],
    ];

    if ($esRefrendo || request()->routeIs('preview.refrendo')) {
        $etapas[] = [
            'nombre' => 'Datos de refrendo',
            'descripcion' => 'Información del refrendo',
            'ruta' => 'registro.refrendo',
            'preview' => 'preview.refrendo',
        ];
    }

    $etapas = array_merge($etapas, [
        [
            'nombre' => 'Formación académica',
            'descripcion' => 'Estudios del solicitante',
            'ruta' => 'registro.formacion',
            'preview' => 'preview.formacion',
        ],
        [
            'nombre' => 'Documentos probatorios',
            'descripcion' => 'Carga de documentos',
            'ruta' => 'registro.documentos',
            'preview' => 'preview.documentos',
        ],
        [
            'nombre' => 'Finalización',
            'descripcion' => 'Registro completado',
            'ruta' => 'registro.completado',
            'preview' => 'preview.completado',
        ],
    ]);

    $rutaActual = request()->route()?->getName();

    $indiceActual = collect($etapas)->search(
        fn ($etapa) => $rutaActual === (
            $esPreview ? $etapa['preview'] : $etapa['ruta']
        )
    );
@endphp

<aside class="cert-sidebar">

    <div class="cert-sidebar-title">
        Proceso de certificación
    </div>
@if($esPreview)
    <div class="cert-preview-aviso">
        <div class="cert-preview-titulo">
            <span>◉</span>
            Vista de demostración
        </div>

        <p>
            Entorno de prueba para revisión
            de pantallas y funcionalidades.
        </p>
    </div>
@endif
    <nav aria-label="Etapas de certificación">

        @foreach ($etapas as $indice => $etapa)

            @php
                $nombreRuta = $esPreview
                    ? $etapa['preview']
                    : $etapa['ruta'];

                $existe = Route::has($nombreRuta);

                $activa = $existe
                    && request()->routeIs($nombreRuta);

                $anterior = $indiceActual !== false
                    && $indice < $indiceActual;
            @endphp

            @if ($existe)

                <a
                    href="{{ route($nombreRuta) }}"
                    class="cert-sidebar-item {{ $activa ? 'active' : '' }} {{ $anterior ? 'completed' : '' }}"
                    @if ($activa)
                        aria-current="step"
                    @endif
                >
                    <span class="cert-step-number">
                        {{ $anterior ? '✓' : $indice + 1 }}
                    </span>

                    <span class="cert-step-text">
                        <strong>{{ $etapa['nombre'] }}</strong>
                        <small>{{ $etapa['descripcion'] }}</small>
                    </span>
                </a>

            @else

                <div class="cert-sidebar-item unavailable">

                    <span class="cert-step-number">
                        {{ $indice + 1 }}
                    </span>

                    <span class="cert-step-text">
                        <strong>{{ $etapa['nombre'] }}</strong>
                        <small>No disponible</small>
                    </span>

                </div>

            @endif

        @endforeach

    </nav>

</aside>
