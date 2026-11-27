# Regresión funcional y visual de 8086 · actual frente a candidato (PHP 8.5.12 + certificacion_anfeca) · 2026-11-27

**Objetivo:** que la actualización sea tecnológica y de base de datos, sin cambios funcionales, visuales ni de navegación no autorizados.

**Dictamen:** el candidato **no presenta regresiones** frente al 8086 actual, una vez corregidas las dos encontradas (§6). Las etapas A y B están completas. **La etapa C (después de conmutar) no se ha ejecutado**, porque la conmutación requiere `sudo` (ver `INTEGRACION_CERTIFICACION_ANFECA_8086.md` §10). La conmutación sigue sujeta, además, a las autorizaciones pendientes: los 3 renombres PDM y D-11 (este último solo para la migración PDM).

## Resumen

| Concepto | Número |
|---|---:|
| Casos ejecutados | **196**: 5 inventarios, 40 páginas visuales en 3 objetivos, 97 pasos funcionales comparados, 13 filas de persistencia, 2 sesiones expiradas, 3 suites PHPUnit (306 pruebas), 36 directivas PHP |
| Casos aprobados | 196 (todos idénticos o con diferencias esperadas) |
| Casos fallidos (después de corregir) | 0 |
| Casos no ejecutados | Etapa C completa (requiere conmutación con root); envío real de correo de recuperación (el correo está configurado como `array`); espera real de 120 min (se simuló con la expiración de la sesión); Apache delante del candidato (requiere root; se usó nginx → PHP-FPM) |
| Diferencias visuales finales | 0 de 40 páginas (fechas y horas normalizadas) |
| Regresiones corregidas | 2: imagen del inicio de sesión y configuración PHP del runtime |
| Regresiones pendientes | 0 |

## 1. Entornos comparados (mismas condiciones)

| Etapa | Objetivo | PHP | Código | Base | Servidor web |
|---|---|---|---|---|---|
| Referencia | `http://192.168.7.130:8086` en vivo, **solo lectura** | PHP-FPM 8.4.27 (pool `www`) | `/var/www/ANFECA_ENSAYO` (`ddd4c8d` + cambios locales) | `bd_anfeca_ensayo_pruebas_pg` | Apache |
| A (escrituras) | Copia del código actual, 127.0.0.1:28086 | PHP-FPM **8.4.27** del sistema (proceso propio con `/etc/php.ini` y `99-server.ini`) | Copia exacta del árbol vivo | `comparacion_base` (copia de `certificacion_anfeca`) | nginx → FPM |
| B | Candidato, 127.0.0.1:18086 | PHP-FPM **8.5.12** (imagen corregida, §6) | Artefacto `anfeca8086_0d3ad60.tar.gz` (SHA-256 `83e25d18…`) | `comparacion_candidato` (copia idéntica) | nginx → FPM |

Identidad por FastCGI: ambos con 512M/20M/America/Mexico_City, `display_errors` Off, PDM apagado y catálogo `ANUIES-2000-2026-LIC-PRELIMINAR-V2`. Las dos bases son copias de `certificacion_anfeca`, cuyas huellas coinciden con el origen en las 75 tablas.

## 2. Inventario funcional actual frente al candidato

| Inventario | Resultado |
|---|---|
| Rutas (`route:list --json`): método, URI, nombre, controlador y middlewares | **52 = 52**, sin diferencias |
| `resources/views` (Blade), `resources/js` (Vue), `resources/css`, `lang`, `public/css`, `public/js`, `public/images` | **Idénticos** (SHA-256 por archivo, sin los `.bak` locales) |
| Controladores | Idénticos. Los únicos `.php` de aplicación que difieren son los de PDM (modelos, servicios de migración, comandos), inactivos con PDM apagado |
| Servicios de catálogos y formación (`CatalogoLicenciaturas`, `CatalogoPosgrados`, `RequisitosFormacion`) | Solo cambia la comprobación de columnas (`FormacionCatalogoPdm::persistenciaDisponible`); con PDM apagado equivale a `Schema::hasColumn` en `public` |
| Configuración | Solo difieren `anfeca_modelo.php` y `anfeca_pdm_decisiones.php` (PDM; el archivo local de 8086 contiene decisiones ficticias que no se trasladan) |
| JS compilado | `app-ZPlUBWH0.js`, `app-DChScZKL.css`, PDF.js, fuentes: **idénticos byte a byte** |
| CSS global (`resources/css/app.css`) | `app-DkoF1sq8.css` → `app-B--N7Oas.css`. Faltan 6 utilidades Tailwind (`break-all`, `lg:px-8`, `sm:justify-start`, `sm:pt-0`, `sm:px-6`, `text-black`), generadas por vistas compiladas de `vendor` (correo de notificación y página de error mínima, que no cargan `app.css`) y por `welcome.blade.php`, que ninguna ruta usa. **Sin impacto**: las 40 capturas son idénticas |
| Cambios locales sin registrar en 8086 | Filtro por área de licenciaturas (`49b100f`); vista de inicio de sesión e imagen `ANFECA.jpg.jpeg` (`0d3ad60`); catálogo V2 (se conserva, configurado por `.env`); `.bak` locales (no se trasladan) |
| Sesión | `file`, 120 min, `expire_on_close=false`, `http_only`, `same_site=lax`: iguales |

## 3. Matriz de pruebas visuales (Playwright, 1366×768, 820×1180 y 390×844)

Por página se compararon: captura (diferencia de píxeles con umbral 40 por canal), URL final, HTTP, título, texto visible, campos de formulario y obligatoriedad, menú, botones, estilos computados, dimensiones, imágenes y errores JavaScript.

| Grupo | Páginas | Actual frente a candidato |
|---|---|---|
| Invitado | Inicio de sesión (3 resoluciones), recuperación, registro de datos personales, validación de inicio de sesión vacío, 404, ruta protegida | **Idénticas** |
| Solicitante | Tras el inicio de sesión, mis datos, certificación, formación (escritorio, tableta y móvil), lista de licenciaturas abierta, formación con posgrado y su lista, formación autorizada, documentos (bloqueado y disponible), compromiso, finalización, acceso denegado a Coordinación | **Idénticas** |
| Coordinación nacional | Dashboard, bandeja (3 resoluciones y filtro de zona), expedientes 3, 4, 5 y 10, **visor PDF** (paginación, zoom, ajustar, pantalla completa), seguimiento, revisión documental desactivada | **Idénticas** |
| Coordinación de zona | Bandeja, expediente ajeno (404), cierre de sesión, ruta protegida después de cerrar | **Idénticas** |

Resultado final: **40/40 idénticas**. Los «errores JS» de 404 y 403 son el estado HTTP de la propia página y aparecen igual en ambos.

Capturas e imágenes de diferencias, privadas porque los expedientes contienen datos personales:
- `~/anfeca_trabajo/evidencias_regresion/visual_actual/` (línea base de la etapa A, también referencia de la etapa C);
- `visual_candidato3/` y `visual_base_copia2/`;
- `comparacion_final_actual_vs_candidato/` (`comparacion.json` y `dif_*.png`).

## 4. Matriz de pruebas funcionales (escritura en copias, cuentas ficticias)

`scripts/anfeca/pruebas/regresion_funcional.py`, ejecutado con la misma secuencia en A y en B. Registra por paso: método, ruta, HTTP, redirección, mensajes visibles, PDF y descarga.

| Elemento | Sistema actual (A) | Sistema candidato (B) | Resultado |
|---|---|---|---|
| Pantallas y rutas (97 pasos) | Secuencia registrada | Misma secuencia | **Sin cambios** |
| Validaciones | Inicio de sesión vacío o incorrecto; registro incompleto; CURP duplicada; autorización sin confirmar; certificado ajeno o inexistente; formación sin documentos; D-LIC-1; archivo de 8 MB («must not be greater than 5120 kilobytes»); documentos sin archivo o de tipo no permitido; refrendo sin artículo; posgrado sin documentos | Mismos mensajes | **Sin cambios** |
| Resultado HTTP | 302, 200, 403, 404 y 422 según el caso | Iguales | **Sin cambios** |
| Datos consultados | Catálogos (51 de Contaduría, 161 de Otro, 40 posgrados), bandeja (claves, orden y paginación), expedientes, seguimiento | Iguales | **Sin cambios** |
| Indicadores | Mismas cifras | Iguales salvo la hora de generación y el host de los enlaces | **Diferencia menor sin impacto** |
| Archivos | Acuse PDF (adjunto), visor PDF/PNG/JPG, descargas `?descargar=1` como adjunto, documento histórico con huella igual | Iguales | **Sin cambios** |
| Permisos | Solicitante sin acceso a Coordinación (403), acuse ajeno (404), zona fuera de alcance (404), filtro de otra zona (403), filtro manipulado (422) | Iguales | **Sin cambios** |
| Mensajes | Éxitos, bloqueos («La formación académica está finalizada…», «Los documentos probatorios ya fueron guardados…») y acuse | Iguales | **Sin cambios** |
| Persistencia | 13 filas: solicitudes, selecciones, datos editados antes de autorizar, formación con clave y versión V2, documentos, refrendo, histórico | Idénticas | **Sin cambios** |
| Sesión e inactividad | Expiración simulada → `/login`; cierre de sesión → `/login` | Iguales | **Sin cambios** |
| PHPUnit | — | SQLite 205 (1 omitida), PostgreSQL 79/79, revisión 22/22 (imagen con `php.ini` de producción) | **Aprobado** |

## 5. Diferencias encontradas y clasificación

| # | Diferencia | Clasificación | Acción |
|---|---|---|---|
| 1 | El inicio de sesión del candidato mostraba `evento_anfeca.jpg`; 8086 muestra `ANFECA.jpg.jpeg` (cambio local sin registrar) | **Regresión visual** | Corregida (`0d3ad60`): se integran la vista y la imagen sin modificarlas |
| 2 | La imagen PHP 8.5.12 no tenía `php.ini`: `display_errors=1`, `log_errors=0`, `zend.exception_ignore_args=0`, `upload_max_filesize=6M` (8086: 20M), zona horaria UTC, `max_execution_time` sin el límite de 60 s, `variables_order`/`request_order`, `short_open_tag` y `session.gc_divisor` distintos | **Regresión funcional** (mensajes de carga distintos entre 6 y 20 MB; riesgo de mostrar errores) | Corregida (`8c88dac`): `php.ini-production` y los valores de `/etc/php.d/99-server.ini`. Solo quedan `pcre.jit` y dos directivas nuevas de 8.5 con sus valores por omisión |
| 3 | Extensiones de 8.4 ausentes en 8.5.12 (`bz2`, `calendar`, `exif`, `ftp`, `gettext`, `shmop`, `sysv*`, `xsl`) | Diferencia menor sin impacto | Sin uso en `app`, `config`, `routes`, `resources` ni en las dependencias en tiempo de ejecución; `check-platform-reqs` correcto |
| 4 | 6 utilidades Tailwind menos en el CSS global | Diferencia menor sin impacto | §2; 40 capturas idénticas |
| 5 | `expose_php` desactivado en el pool exclusivo (8086: encabezado `X-Powered-By: PHP/8.4.27`) | Diferencia menor sin impacto (no visible; mejora de seguridad) | Se conserva desactivado |
| 6 | Indicadores: hora de generación y host de los enlaces | Diferencia esperada | — |

Diferencias del **entorno de prueba**, no del candidato (corregidas en el laboratorio sin tocar la aplicación):
- nginx no declaraba el tipo MIME de `.mjs`, que el visor PDF necesita; Apache de 8086 lo sirve y seguirá haciéndolo;
- etiqueta SELinux de archivos agregados con el contenedor ya en ejecución; el pool de producción usa `label=disable`;
- archivos de los folios ficticios 9–11 ausentes en la copia. Se obtuvieron por HTTP o se reconstruyeron con su contenido ficticio conocido, verificado con el folio 11.

## 6. Correcciones y pruebas repetidas

| Corrección | Commit | Prueba repetida |
|---|---|---|
| Vista e imagen del inicio de sesión | `0d3ad60` | Inicio de sesión en 3 resoluciones idéntico a 8086; artefacto `0d3ad60` reconstruido (`app-ZPlUBWH0.js`) |
| Configuración PHP del runtime | `8c88dac` | Directivas iguales; caso del archivo de 8 MB con el mismo mensaje; PHPUnit 205/79/22 |
| Artefacto exigido explícitamente (`ANFECA_COMMIT`) y verificación de la compilación aprobada; etapa C en la conmutación | Este commit | Sintaxis de los scripts validada |

## 7. Defectos preexistentes (también en 8086; no se reproducen como requisito ni se corrigen sin autorización)

1. `tipo_personal` y `area_certificacion` solo se validan como texto (máximo 100 y 150 caracteres): un valor inexistente se acepta (`SeleccionCertificacionController`, líneas 185–198).
2. Varios mensajes de validación aparecen en inglés («The email field is required», «The curp field must be 18 characters», «must not be greater than 5120 kilobytes»): faltan las traducciones de `validation`.
3. Las bandejas de coordinación por zona quedan vacías mientras `instituciones_afiliadas.zona_id` siga en NULL (normalización de zonas pendiente de autorización).
4. Apache sirve `.mjs` sin `Content-Type`. Funciona en los navegadores probados, pero conviene declararlo.

## 8. Defectos nuevos

Ninguno pendiente. Los dos encontrados (§5, 1 y 2) se corrigieron y se volvieron a probar.

## 9. Etapa C (después de conmutar)

`02_conmutar_root.sh` imprime los comandos. Repetir `regresion_visual.js` sobre 8086 y compararlo con `visual_actual` usando `comparar_regresion_visual.js`. Además, ejecutar `e2e_candidato.py` con `ANFECA_E2E_PERMITIR_8086=si` (solo registros ficticios). Si alguna página difiere de la línea base, salvo fechas, se ejecuta `03_revertir_root.sh`.

## 10. Dictamen final

- **Funcional y visual:** el candidato (PHP-FPM 8.5.12 + `certificacion_anfeca`, artefacto `0d3ad60`) conserva las mismas pantallas, funciones, información, documentos, reglas, validaciones, mensajes, permisos y experiencia que el 8086 actual. **APROBADO para conmutar desde el punto de vista de regresión.**
- **Conmutación:** **NO EJECUTADA.** Requiere la intervención administrativa (root) y respetar las autorizaciones pendientes del modelo PDM (`INTEGRACION_CERTIFICACION_ANFECA_8086.md` §10). La etapa C es obligatoria inmediatamente después.
