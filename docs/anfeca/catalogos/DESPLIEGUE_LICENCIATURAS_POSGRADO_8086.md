# Despliegue en 8086 · catálogo de licenciaturas y declaración de posgrado · 2026-11-26

Ambiente de ensayo (`/var/www/ANFECA_ENSAYO`, puerto 8086, base `bd_anfeca_ensayo_pruebas_pg`). 8084 y 8085 sin cambios. PDM apagado; `escuela_facultad` no aplicada; revisión documental desactivada.

## 1. Resultado

| Elemento | Estado |
|---|---|
| Código | `desarrollo/modulo-coordinacion` a6a79e5 (avance rápido desde 44f522a; cambios locales ajenos conservados) |
| Migración | Solo `2026_11_28_000000_add_titulo_origen_especifico_y_declaracion_posgrado` (5 columnas nulas, 2 CHECK validados). `aplicar_licenciaturas_posgrado.sh`: 0 fallas; datos anteriores y 20 documentos idénticos |
| Catálogo de licenciaturas | `storage/app/private/catalogos/licenciaturas_anuies.json` · 9 386 títulos · `ANUIES-2000-2026-LIC-PRELIMINAR` · SHA-256 `7bc8c998…` · 0640 servidorweb:apache, directorio 2750 |
| Catálogo de posgrados | Sin cambios: 1 794 títulos · `ANUIES-2025` · SHA-256 `ed15f4f2…` |
| Compilación | `app-DYxc-NO1.js` (13 recursos con HTTP 200; la anterior `app-CYBBpN_p.js` sigue presente para reversión) |
| Banderas | `ANFECA_CATALOGO_POSGRADOS=true` · `ANFECA_DECLARACION_POSGRADO=true` · `ANFECA_CATALOGO_LICENCIATURAS=true` (+ SHA256 y VERSION) |
| Clasificaciones | Preliminares; no se aprobó ninguna (pendientes: 2 319 licenciaturas y 572 posgrados). Equivalencias normativas sin cambios |

## 2. Compuerta D-LIC-1 y captura manual (antes de activar licenciaturas)

Prueba en memoria con el catálogo real (sin escrituras), 16/16:
- título del catálogo de un área distinta de la que admite la certificación → **se detiene con explicación**;
- clave inexistente → rechazada; título compatible → área del catálogo, origen `catalogo`, clave y versión;
- captura manual → registra **solo** el área que admite la certificación (la misma que el selector anterior de una opción) con origen `manual`, sin clave ni versión. El expediente la muestra como «captura manual», sujeta a revisión de procedencia;
- validaciones del servidor de la captura manual: vacía, menor de 5, mayor de 200, con `<`/`>`, modo desconocido y catálogo + texto simultáneos → rechazadas.

Riesgo residual documentado: quien sea detenido por D-LIC-1 puede usar «No encuentro mi título» (es la ruta prevista en la opción A vigente). El registro queda marcado como `manual` y visible para la coordinación. La decisión de fondo sigue siendo LIC-06 (CNCA).

## 3. Aceptación en 8086

| Prueba | Resultado |
|---|---|
| Pantalla, búsquedas (≥ 3 letras, sin acentos ni mayúsculas, límite con total), teclado, captura manual y regreso, 2 escenarios, D-LIC-1 en vivo, confirmación al desmarcar, tableta y móvil | 23/23 |
| Documentos, expedientes (folios 9, 10 y lectura de 3, 4 y 5 · inicial y refrendos), captura manual inválida rechazada sin cambios | 12/12 (una comprobación de texto se corrigió por base de datos: sin cambios) |
| Flujo completo (usuario de pruebas automatizadas): captura manual válida, autorización, documentos probatorios sin duplicar título ni cédula, formación bloqueada | 5/5 (comprobación final por captura y base de datos) |
| Errores de JavaScript / líneas nuevas en `laravel.log` | 0 / 0 |
| Integridad | 20 documentos idénticos; filas de folios 3–7 y usuarios ≤ 34 sin modificación; solo cambiaron las tablas esperadas |

Evidencias (privadas): `~/ANFECA_EVIDENCIAS/despliegue_licenciaturas_20261126/`. El acuse y el envío están cubiertos por `FormacionLicenciaturaPosgradoTest` y por la suite; en 8086 no se envió ninguna solicitud.

Usuarios ficticios (contraseñas solo en `~/ANFECA_CREDENCIALES/aceptacion_formacion_8086.txt`, 600):
- `aceptacion.licenciatura@example.test`: folio 9, Contaduría Pública, escenario 1, editable;
- `aceptacion.posgrado@example.test`: folio 10, Administración, escenario 2 (Maestría), editable;
- `aceptacion.flujo@example.test`: folio 11, solo para pruebas automatizadas (formación autorizada).

## 4. Mapa técnico (archivo → responsabilidad)

| Capa | Archivo | Métodos o elementos clave |
|---|---|---|
| Configuración | `config/anfeca_catalogos.php` | `licenciaturas.*`, `declaracion_posgrado.activo`, `areas_que_exigen_posgrado` |
| Catálogo | `app/Services/Catalogos/CatalogoLicenciaturas.php` | `datos()`, `estado()`, `activo()`, `porClave()`, `buscar()`, `version()`, `persistenciaDisponible()` |
| Regla de origen | `app/Services/Catalogos/TituloOrigen.php` | `usaCatalogo()`, `reglas()`, `mensajes()`, `aplicar()` (D-LIC-1 y manual), `protegerSinCatalogo()`, `presentar()` |
| Requisitos | `app/Services/Formacion/RequisitosFormacion.php` | `campos()`, `documentos()`, `documentoAplica()`, `soloLicenciatura()`, `declaracionActiva()`, `exigePosgrado()` |
| Endpoint | `app/Http/Controllers/Catalogos/CatalogoLicenciaturasController.php` | `GET /registro/catalogos/licenciaturas` (throttle 120) |
| Controlador | `app/Http/Controllers/FormacionAcademicaController.php` | `create()`, `store()`, `autorizar()`, `verificarSolicitud()` |
| Lectores | Acuse, expediente, seguimiento, documentos aplicables, consulta | Usan `RequisitosFormacion` y `TituloOrigen::presentar()` |
| Vista | `resources/views/registro/formacion-academica.blade.php` | `data-catalogo-licenciaturas`, `data-declaracion-posgrado`, `data-posgrado-obligatorio` |
| Componentes | `resources/js/components/FormacionAcademica.vue`, `SelectorBuscable.vue` | Selector remoto, casilla, avisos, `nivelesUltimo` |
| Persistencia | `database/migrations_propuestas/2026_11_28_…php` | 5 columnas; `fas_titulo_origen_coherente_ck`, `fas_declaracion_posgrado_ck` |
| Operación | `scripts/anfeca/aplicar_/verificar_/revertir_licenciaturas_posgrado.sh`, `desplegar_compilacion.sh`, `respaldo_verificado.sh`, `huellas_documentos.sh`, `huella_bd.sh` | — |

## 5. Atención de incidentes

| Síntoma | Causa probable | Acción |
|---|---|---|
| El selector muestra «catálogo no disponible» | Archivo ausente, SHA-256 distinto o permisos | `sha256sum storage/app/private/catalogos/licenciaturas_anuies.json`; 0640 servidorweb:apache; comparar con `.env` |
| La pantalla no muestra la casilla ni el selector | Compilación anterior servida o banderas apagadas | Revisar `public/build/manifest.json` (`app-DYxc-NO1.js`) y `grep ^ANFECA_ .env` |
| «No existe una solicitud válida» al entrar a Formación | Falta `selecciones_certificacion` del usuario | Completar el paso Certificación |
| Error de CHECK al guardar | Datos incoherentes (catálogo sin clave o declaración sin nivel) | Revisar la petición; los CHECK son la última defensa |
| Se requiere volver a la pantalla anterior | — | Apagar las banderas (efecto inmediato) y, si hace falta, restaurar la compilación: procedimiento en `~/ANFECA_RESPALDOS/despliegue_licenciaturas_20261126_192422/LEEME.md` |

## 6. Matrices para revisión del responsable (privadas, fuera de Git)

- `~/ANFECA_FUENTES/licenciaturas/matriz_clasificacion_licenciaturas_consolidada.xlsx` (y `resumen_revision_licenciaturas_consolidada.xlsx`)
- `~/ANFECA_FUENTES/licenciaturas/matriz_clasificacion_licenciaturas.xlsx` (ciclo 2025-2026)
- `~/ANFECA_FUENTES/matriz_clasificacion_posgrados.xlsx` (y `resumen_revision_posgrados.xlsx`)
