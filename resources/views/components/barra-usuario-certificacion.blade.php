<div class="barra-usuario barra-usuario-certificacion">
    <div class="barra-usuario-identidad">
        <span class="icono-usuario">◉</span>
        <span>
            Usuario: {{ auth()->user()?->name ?? 'Vista previa' }}
        </span>
    </div>

    @auth
        <form method="POST"
              action="{{ route('logout') }}"
              class="form-cerrar-sesion">
            @csrf

            <button type="submit"
                    class="boton-cerrar-sesion">
                Cerrar sesión
            </button>
        </form>
    @endauth
</div>
