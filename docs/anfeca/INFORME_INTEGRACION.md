# Informe de integración — `integracion/anfeca-bd-final`

**Fecha:** 20/11/2026
**Base:** `desarrollo/anfeca-pruebas` @ `6c873c2` («nueva pantalla de autorizacion»)
**Origen de lo integrado:** commit `0126d22` (`ANFECA_BD_final`), aplicado con `cherry-pick -x`
**Trabajo:** worktree independiente. No se tocó `main`, `desarrollo/anfeca-pruebas` ni el directorio del servidor. No se ejecutó el SQL del PDM en ninguna BD del proyecto.

> Este informe **sustituye** al `INFORME_ANFECA_BD_final.md` en lo referente al estado del código, porque aquel se hizo sobre `main` (03/nov). Su evaluación de vulnerabilidades se actualiza en la sección 5.

---

## 0. Acción urgente: copia de la base de datos en un repositorio público

- `Quarito/SANFECA` es **público**: la API de GitHub devuelve `"visibility": "public"`.
- La rama `desarrollo/anfeca-pruebas` versiona volcados de PostgreSQL, desde el commit `b481650` (17/11):

| Archivo | Tamaño | Contenido |
|---|---|---|
| `respaldos_demo/anfeca_demo_20261117_001625.dump` | 86 KB | Volcado `pg_dump -Fc` con **datos de 25 tablas**. No se extrajo su contenido. |
| `respaldos_demo/anfeca_antes_demo_20261117_000129.dump` | 0 B | Vacío |
| `respaldos_usuario_k/antes_reinicio_k_*.dump` (3) | 0 B | Vacíos |

**Por qué importa:**
- Un volcado de la BD de pruebas suele incluir hashes de contraseñas (`users`), datos de identidad (`datos_solicitantes`: CURP, RFC, domicilio, teléfonos), `sessions` y `password_reset_tokens`.
- También incluye `certificados_historicos`, que proviene de un Excel histórico y **podría contener personas reales**.
- Cualquiera puede descargarlo.

**Qué hacer**, en este orden. Ninguno de estos pasos se ejecutó, porque cambian el repositorio o la configuración de GitHub y requieren tu decisión.

1. **Hacer privado el repositorio** en GitHub: Settings → General → Danger Zone → Change visibility. Detiene la exposición.
2. **Comprobar qué contiene el volcado**, en el servidor y sin compartir la salida, para saber si hay personas reales:

   ```bash
   pg_restore -l respaldos_demo/anfeca_demo_20261117_001625.dump | grep "TABLE DATA"
   ```

3. **Quitarlo del árbol** en una rama de limpieza:

   ```bash
   git rm --cached respaldos_demo/*.dump respaldos_usuario_k/*.dump
   ```

   Después, agregar `*.dump` y `respaldos_*/` a `.gitignore`. Conserva los archivos en el servidor, fuera de Git.
4. **Si hay datos reales**, además:
   - purgar el historial con `git filter-repo`, coordinando con todos los clones;
   - forzar el cambio de contraseña de esas cuentas;
   - invalidar sesiones y tokens de recuperación;
   - valorar la notificación conforme a la normativa de protección de datos personales aplicable a la institución.

Otros archivos versionados que conviene retirar en la misma limpieza:
- **Captura de un acuse:** `:USERPROFILE\Downloads\anfeca_acuse_vista_917.png` (PNG de 993×1404). Puede mostrar datos de un solicitante.
- **Salida de tinker:** `elect(`, con un volcado de consulta.
- **Archivos sueltos de la raíz:** 14 archivos vacíos (`,`, `[`, `curp`, `false,`, textos de mensajes…) y 2 copias de la ayuda de `less` (`t')`, `tructura_formacion_anfeca'`). Son accidentes de terminal.
- **`respaldos_interfaz/`:** copias de Blade y CSS.

---

## 1. Qué contiene la rama de integración

```text
6c873c2  (base) nueva pantalla de autorizacion          ← desarrollo/anfeca-pruebas
<cp>     Integrar PDM PowerDesigner en esquema aislado  ← cherry-pick -x de 0126d22 (12 archivos A)
<adj>    Ajustar integración al código vigente           ← este trabajo
```

| Archivo | Tipo | Detalle |
|---|---|---|
| Los 12 archivos de `0126d22` | Nuevos | SQL aislado, comandos, servicio, config, pruebas del PDM, documentación |
| `config/anfeca_modelo.php` | Ajustado | El mapeo apunta a las tablas vigentes por solicitud: `solicitudes_certificacion`, `datos_refrendo`, `formaciones_academicas_solicitud`, `documentos_probatorios_solicitud`, roles y privilegios. Se agregó `estado_solicitud`. |
| `app/Console/Commands/AnfecaModelo*.php` | Ajustado | Sin conexión a la BD muestran un mensaje breve, sin traza ni credenciales, y salen con código 1 |
| `tests/Feature/RegresionAnfecaTest.php` | Nuevo | 18 pruebas de regresión con usuarios ficticios |
| `docs/anfeca/PRUEBAS_REGRESION.md` | Nuevo | Pruebas automatizadas y protocolo manual de 15 casos |
| `docs/anfeca/PARCHES_PENDIENTES.md` | Reescrito | Los 9 parches comparados contra `6c873c2` |
| `docs/anfeca/PASOS_SERVIDOR.md` | Ajustado | Flujo con worktree de revisión, PR de avance rápido y diagnóstico antes de instalar |
| `docs/anfeca/INFORME_INTEGRACION.md` | Nuevo | Este informe |

**Comprobado:**
- `git diff --name-status 6c873c2 HEAD` muestra **solo `A`**.
- Ningún controlador, modelo, vista Blade, componente Vue, ruta ni migración existente fue modificado.

---

## 2. Comparación de los 9 parches contra el código vigente

| # | Estado en `6c873c2` |
|---|---|
| P1 registro 500 | Resuelto |
| P2 regla de edición | Resuelto (backend y Vue) |
| P3 al menos un teléfono | Resuelto |
| P4 `/registro` 500 | **Sigue** (confirmado) |
| P5 `guest` | **Cambió:** un usuario con datos que reenvía el registro produce un 500 por unicidad (confirmado) |
| P6 contraseña mínima de 8 | **Sigue** (confirmado) |
| P7 antecedentes descartados | Resuelto |
| P8 historial | **Parcial:** el último grado está cubierto; faltan los probatorios y el origen |
| P9 `ExampleTest` | **Sigue** |

El detalle y el código de cada parche están en `PARCHES_PENDIENTES.md`. **No se aplicó ninguno.**

---

## 3. PowerDesigner en modo diagnóstico (sin ejecutar el SQL)

Entorno:
- PostgreSQL 16 local del contenedor, una base desechable con el mismo nombre (`bd_laravelanfeca_pruebas`), **no** la del servidor.
- Las 34 migraciones vigentes aplicadas: 25 tablas en `public`.

| Comprobación | Resultado |
|---|---|
| `php artisan migrate` (34 migraciones vigentes) en PG16 | Todas `Ran`, sin errores |
| `anfeca:modelo-instalar` (en seco) | Revisión estática correcta (36 tablas, 35 FK, sin DROP ni `public`), BD permitida, **no crea nada** |
| `anfeca:modelo-verificar` | 22 entradas: 14 OK y 8 DIVERGE. **Ninguna tabla ni columna Laravel faltante.** Las divergencias son conceptos del PDM sin equivalente: folio de solicitud, motivo de rechazo, año de refrendo, `PERS_ACTIVO`, `USUA_ESTATUS`, `INST_FACULTAD`, cédula en estudios, folio y área en el histórico. |
| Esquema `anfeca_modelo` después del diagnóstico | **No existe**: confirmado |
| Comando sin conexión (puerto cerrado) | Mensaje claro y código 1 |

**Siguiente decisión funcional (fase 1 del mapeo):** para cada una de las 8 divergencias, decidir si Laravel debe incorporar el dato. Ejemplos: el folio de solicitud del acuse y el historial de estados con motivo de rechazo. Cada incorporación sería una migración aditiva.

---

## 4. Pruebas

| Suite | Resultado |
|---|---|
| `RegresionAnfecaTest` (nueva) | **18/18** |
| `AnfecaModeloSqlTest` | 10/10 |
| `MigracionesAnfecaTest`, `AislamientoAnfecaTest` (existentes) | 2/2 |
| `Unit/ExampleTest` | 1/1 |
| `Feature/ExampleTest` (plantilla) | 0/1, preexistente (P9) |
| **Total** | **31/32** |

**Funcionalidades validadas que quedan protegidas:**
- Registro.
- Reglas de datos personales y autorización.
- Middleware.
- Aceptación Vue: texto literal, «Anterior», casilla obligatoria.
- Bloqueo de Finalización sin compromiso.
- Envío: requisitos, compromiso, no duplicado, aislamiento.
- Acuse solo de solicitudes propias y enviadas.
- Etapa 1: documentos con formación autorizada.
- Descarga aislada.
- Conservación del archivo reemplazado.

**Pendiente de prueba manual:** refrendo con antecedente, equivalencias, «Otro», PDF del acuse. Ver `PRUEBAS_REGRESION.md`, sección B.

**Nota sobre el entorno:**
- El `composer.lock` exige PHP ≥ 8.4.1. Las pruebas se ejecutaron en una copia con dependencias resueltas para PHP 8.3; el lock del repo no se modificó.
- `composer audit` del lock vigente: **sin avisos** (Laravel 13.42, Symfony 8.1.11). V3 del informe anterior queda resuelto.

---

## 5. Vulnerabilidades actualizadas

| ID | Severidad | Estado en `6c873c2` |
|---|---|---|
| **V0** | **Crítica** | **Nueva:** volcado de BD y captura de acuse en un repositorio público (sección 0) |
| V1 | Alta | `APP_DEBUG` y `APP_ENV=local`: verificar en el servidor (`php artisan about`). Con `local` se activan las rutas `/preview/*` sin autenticación. |
| V2 | Alta | HTTP sin TLS en la LAN: sin cambios |
| V3 | — | **Resuelto:** dependencias sin avisos |
| V4 | — | **Resuelto** (P2) |
| V5 | Media | `/registro` sigue en 500 (P4). Nuevo: registro duplicado con sesión iniciada → 500 (P5). |
| V6 | Baja | Archivos: aislamiento y validación correctos (lo cubren las pruebas); falta el historial de probatorios (P8) |
| V7 | Baja | Contraseña mínima de 8 en la recuperación (P6) |
| V8–V9, V11–V13 | Baja/Info | Sin cambios respecto al informe anterior |
| V10 | Media (antes Baja) | Higiene del repositorio: a los respaldos versionados se suman volcados de BD, archivos de terminal y capturas |

---

## 6. Recomendación de siguientes pasos

1. **Hoy:**
   - Hacer privado el repositorio.
   - Evaluar el contenido del volcado y retirarlo del árbol (sección 0).
2. Revisar la rama de integración en un worktree del servidor (`PASOS_SERVIDOR.md`, pasos 2 y 3) y fusionarla por PR, con avance rápido, en `desarrollo/anfeca-pruebas`.
3. Ejecutar el diagnóstico de solo lectura en el servidor (paso 3b) y compartir la tabla.
4. Aplicar P4, P5 y P6 en una rama `fix/…`, de uno en uno, ampliando `RegresionAnfecaTest` en cada uno.
5. Seguir el protocolo manual B (refrendo 3.5.3 y 3.5.4).
6. Instalar `anfeca_modelo` (pasos 4 a 6) **solo** con un respaldo `pg_dump` verificado y restaurado.
