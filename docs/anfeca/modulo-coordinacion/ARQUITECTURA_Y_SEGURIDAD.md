# Módulo de Coordinación · Fases 6.1 y 6.2 · Arquitectura y seguridad

## 1. Arquitectura

```
Navegador ──► /coordinacion/*  [web → auth → coordinacion (Gate acceder-coordinacion)]
                 │
   InicioController ── vista Blade ── Vue CoordinacionInicio ──► GET /coordinacion/api/indicadores
   SolicitudesController ── vista Blade ── Vue BandejaSolicitudes ─► GET /coordinacion/api/solicitudes
   ExpedienteController (Policy) ── consulta básica y descarga de documentos (solo lectura)
                 │
         ConsultaSolicitudes  ◄── AlcanceZona (zonas autorizadas, solo servidor)
                 │
   PostgreSQL (public): solicitudes_certificacion ⟶ formaciones_academicas_solicitud
       ⟶ instituciones_afiliadas ⟶ zonas ; datos_solicitantes ; certificados_historicos ; users
```

Los indicadores, la bandeja, el expediente y las descargas usan **la misma consulta con alcance**
(`App\Services\Coordinacion\ConsultaSolicitudes`). Así los totales del dashboard coinciden con los de
la bandeja y ninguna vista puede ampliar el alcance de otra.

El PDM sigue apagado y no se crearon tablas ni columnas.

## 1.1 Layout administrativo común (homologado con el módulo de solicitantes)

Todas las vistas administrativas, actuales y futuras, extienden `resources/views/coordinacion/layout.blade.php`:

| Pieza | Origen |
|---|---|
| Encabezado institucional | `components/anfeca/encabezado-institucional.blade.php`: mismo marcado y textos que las pantallas de solicitantes |
| Pie de página institucional | `components/anfeca/pie-institucional.blade.php`: mismos textos («Hecho en México», D.R., aviso y administración del sitio) |
| Barra de usuario | `coordinacion/partials/barra-usuario.blade.php`: clases `barra-usuario barra-usuario-certificacion`, más rol y alcance |
| Menú lateral | `coordinacion/partials/menu.blade.php` (con `config/anfeca_coordinacion.php`) |
| Hojas institucionales | `certificacion.css`, `barra-usuario-certificacion.css`, `tipografia-global-anfeca.css`, `anfeca-layout-compacto*.css`, `anfeca-avisos.css`, `anfeca-compacto-v4.css` (mismo orden que en solicitantes) |
| Estilos propios | `public/css/coordinacion.css`, con la paleta de `panel-certificacion.css` (`--anfeca-*`) |

Las vistas nuevas solo deben extender el layout y definir `@section('titulo')` y `@section('contenido')`.
También admiten `@push('estilos')` y `@push('scripts')`.

Distribución responsiva. Se usan *container queries* sobre el área de contenido, así que la distribución depende del espacio real y no del ancho de la ventana:

| Elemento | Amplio (≥ 1100 px) | Intermedio (≥ 700 px) | Tableta (≥ 340 px) | Móvil |
|---|---|---|---|---|
| Tarjetas del dashboard (6) | 6 columnas | 3 × 2 | 2 × 3 | 1 |
| Paneles de distribución (4) | 4 columnas | 2 × 2 (desde 640 px) | 1 | 1 |
| Fichas de la consulta | 50 % + 50 %, igual altura | 50 % + 50 % (más de 760 px) | Apiladas: primero el solicitante | Apiladas |
| Documentos | Grupos de 6: 3 columnas con 2 documentos | 2 columnas (hasta 900 px), en pares | 2 columnas | 1 (hasta 560 px) |

Nunca se muestran 5 + 1 tarjetas ni 3 + 1 paneles.

Consulta de solicitud:
- **Ficha del solicitante (50 %):** datos del solicitante y formación académica en la misma tarjeta, separados por un divisor.
- **Datos de solicitud (50 %).** Campos cortos en dos columnas; institución, área y tipo de personal a ancho completo.
- Ambas fichas tienen el mismo ancho y la misma altura en escritorio (fase 6.2.4).

Cuadrícula de documentos:
- Se muestran los documentos aplicables al trámite. Los de artículo 13/14 solo aparecen en refrendos con `aplica_articulo_14`, con el mismo criterio que la pantalla de solicitantes. Los que no aplican se listan como «No aplica».
- Estados de **carga** (no de validación):
  - «Cargado»;
  - «No cargado»;
  - «Registrado, archivo no localizado».
- La leyenda «Revisión documental: pendiente (fase 6.3)» aparece una sola vez en el encabezado de la sección.

Tablas de registros: la primera columna es «Núm.», un consecutivo visual calculado en el servidor. Las reglas están en `docs/anfeca/ESTANDARES_UI_UX.md` §5.

## 2. Roles y permisos (`users.rol`)

| Operación | `coordinador_nacional` | `coordinador_zona` | `solicitante` | Invitado |
|---|---|---|---|---|
| `/coordinacion` (dashboard) | Todas las zonas | Sus zonas | 403 | → login |
| Bandeja / API de solicitudes | Todas, incluidas las «pendientes de clasificación» | Sus zonas; nunca sin zona | 403 | 401 / → login |
| Filtro `zona` fuera de alcance | — | 403 | 403 | — |
| Expediente y documentos | Todos | Sus zonas; fuera de ellas **404** | 403 | → login |
| Flujo de solicitantes (`/mis-datos`, `/registro/*`, `/panel`, `/`) | → `/coordinacion` | → `/coordinacion` | Sin cambios | Sin cambios |
| Escritura sobre solicitudes | No existe | No existe | Flujo propio | — |

- Un coordinador de zona **sin asignación** en `coordinador_zona` no ve ningún registro.
- La zona se determina en el servidor con esta cadena: solicitud → formación (`institucion_afiliada_id`) → institución (`zona_id`) → zona. Ningún parámetro de la petición puede ampliarla.
- El alta pública siempre crea `rol = solicitante`: `rol` no es asignable masivamente y el valor por omisión de la columna es `solicitante`.

Piezas de Laravel:
- Middleware `coordinacion` (`AccesoCoordinacion`) y `solo.solicitantes` (`SoloSolicitantes`).
- Gate `acceder-coordinacion`.
- Policy `SolicitudCertificacionPolicy`: `consultarComoCoordinacion` y `descargarDocumentoComoCoordinacion`.
- Servicio `AlcanceZona`.

## 3. Rutas

| Método | Ruta | Nombre |
|---|---|---|
| GET | `/coordinacion` | `coordinacion.inicio` |
| GET | `/coordinacion/api/indicadores` | `coordinacion.api.indicadores` |
| GET | `/coordinacion/solicitudes` | `coordinacion.solicitudes` |
| GET | `/coordinacion/api/solicitudes` | `coordinacion.api.solicitudes` |
| GET | `/coordinacion/solicitudes/{solicitud}` | `coordinacion.solicitudes.ver` |
| GET | `/coordinacion/solicitudes/{solicitud}/documentos/{campo}` | `coordinacion.solicitudes.documento` |

Parámetros de la API de solicitudes, todos validados en el servidor:
- `q`: hasta 100 caracteres.
- `estado`: `borrador` o `enviado`.
- `tipo_tramite`: `inicial` o `refrendo`.
- `certificacion`: `ANFECA`, `CACSLA` o `CACECA`.
- `zona`: id numérico o `sin_zona`.
- `institucion`: id numérico.
- `orden`: lista blanca de columnas.
- `direccion`: `asc` o `desc`.
- `pagina`: número de página.
- `por_pagina`: 10, 25 o 50.

## 4. Indicadores

| Indicador | Cálculo |
|---|---|
| Usuarios registrados (nacional) | `users` sin roles de coordinación |
| Solicitantes en sus zonas (zona) | Personas distintas con al menos una solicitud en sus zonas |
| Solicitudes / iniciadas / enviadas | `estado` (`borrador` / `enviado`); una incompleta nunca cuenta como enviada |
| Primera vez / refrendos | `tipo_tramite` |
| Distribución por zona, institución, certificación y tipo | Misma consulta, agrupada |
| Recientes | Últimas 5 por fecha de envío o, si no existe, de creación |
| Por revisar, en evaluación, con observaciones, aceptadas, no aceptadas, avance | **Pendiente de implementación** (valor `null`, nunca 0) |
| Convocatoria | Pendiente de validación: la solicitud no tiene convocatoria asociada |

## 5. Bandeja

- **Columnas mostradas:** folio (id de la solicitud, el mismo del acuse), zona, nombre, institución, facultad, modalidad, certificación, tipo de personal, área de conocimiento, certificado histórico (solo refrendos), fecha de envío, estado y acción «Consultar».
- **Pendientes:** Participación (sin definición funcional), Calificación (fase 6.4) y Convocatoria (pendiente de validación).
- **Solo lectura:** no existen rutas POST, PUT, PATCH ni DELETE en `/coordinacion`.

## 6. Validación

```bash
php artisan test --filter CoordinacionTest      # SQLite en memoria, datos ficticios
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.xml
npm run build
php artisan route:list --path=coordinacion
```
