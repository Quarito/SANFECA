# Revisión CAL-01, preparación del despliegue de Formación Académica y estado operativo · 2026-11-26 (noche)

**Base:** `a6a79e5` (último desarrollo publicado). **Rama de revisión:** `revision/cal-01-formacion`. 8086 sigue en `44f522a`, con los 4 cambios locales conservados. No se aplicaron migraciones ni se activaron banderas. El PDM sigue `apagado`.

## 1. Recuperación de `a6a79e5`

- Se recuperó en un árbol aislado (`arbol_aislado.sh`), no en `/var/www`. El DocumentRoot de 8086 es `/var/www/ANFECA_ENSAYO/public`: un *fast-forward* del árbol servido **equivale a desplegar**.
- Los 12 commits entre `44f522a` y `a6a79e5` no se solapan con los cambios locales (`config/anfeca_pdm_decisiones.php`, `resources/views/auth/login.blade.php` y 2 archivos sin rastrear).
- **Regresión de `a6a79e5`:** SQLite, 195 pruebas: 194 correctas y 1 omitida (preexistente), con PHP 8.4.27.

## 2. Revisión técnica de Formación Académica

| Tema | Resultado |
|---|---|
| Escenario 1 (solo licenciatura) | Correcto. Solo 2 documentos; las columnas de posgrado quedan NULL; el último grado se deriva |
| Escenario 2 (licenciatura con posgrado) | Correcto. Nivel ∈ {Especialidad, Maestría, Doctorado} en Laravel y en el CHECK |
| Casilla de posgrado | Correcto. Al desmarcarla se conservan los datos (no vigentes); si el formulario no envía la casilla, rige la declaración guardada |
| Equivalencias | Sin cambios en `config/anfeca_equivalencias.php`. D-LIC-1 detiene con explicación (decisión de la CNCA pendiente) |
| Refrendos | Se conserva el antecedente con área histórica; en un refrendo sin formación previa se usa el catálogo |
| Rollback | Por banderas (principal), por `git revert` de los commits de interfaz, por manifiesto de compilación, o estructural solo con columnas vacías. **Sin la migración, todo funciona como antes** (prueba `test_sin_la_migracion_nueva_todo_funciona_como_antes`) |
| **Defecto encontrado (corregido en `362adcf`)** | Un solicitante podía guardar «solo licenciatura» en un área que no exige posgrado y **después cambiar el área de certificación** a «Disciplinas vinculadas…» (`SeleccionCertificacionController` actualiza el área sin revisar la formación). La autorización y el envío final aceptaban la formación sin posgrado, contra DOC-02 (Reglamento, arts. 1 y 13; Instrumento 1.1). Se reprodujo: sin la corrección, la autorización redirige a Documentos |
| Valor de la casilla | Un `0` numérico (petición manipulada o JSON) se trataba como escenario 2. Ahora se normaliza a texto y un arreglo lo rechaza la validación |

## 3. Revisión puntual CAL-01

| Criterio | Hallazgo | Acción |
|---|---|---|
| Separación de responsabilidades (PHP) | Reglas y 6 mensajes de la declaración incrustados en `FormacionAcademicaController::store` | Se movieron a `RequisitosFormacion::reglasDeclaracion()` y `mensajesDeclaracion()` (fuente única). Se agregaron `faltaPosgradoObligatorio()` y `mensajePosgradoObligatorio()` |
| Código entendible | 14 nombres completos de clase (`\App\Services\...`) dentro de los métodos | Sustituidos por `use` en ambos controladores |
| Vue / JavaScript | `FormacionAcademica.vue` separa estado, búsqueda remota y casilla; el navegador solo envía la clave. `app.js` solo pasa las propiedades | Sin cambios |
| CSS | Bloque `<style>` **no encapsulado** con colores fijos (`#9e1b32`, `#326b48`, `#7a4b00`), acotado con `#vue-formacion-academica` | **No se modificó**: la vista ya está validada visualmente (25 comprobaciones). Propuesta para la siguiente iteración visual: variables CSS del estándar UI/UX |
| Indentación | Mezcla de tabuladores y espacios en los controladores (preexistente) | No se reformateó (evita un diff masivo sin cambio funcional) |
| Documentación técnica | Este documento y los comentarios en los métodos nuevos | — |
| Atención de incidentes | Ver §4 | Corregido |

**Pruebas después de CAL-01:**
- SQLite: 196 pruebas, 195 correctas y 1 omitida (+1 prueba nueva: `test_cambio_de_area_a_una_que_exige_posgrado_detiene_autorizacion_y_envio`);
- Coordinación, revisión documental, esquema y zonas: 69 pruebas, 68 correctas y 1 omitida;
- `FormacionLicenciaturaPosgradoTest`: 14/14.

**Bloqueada por espacio:** la prueba PostgreSQL desechable (`probar_migracion_licenciaturas.sh`) exige 5 GB libres y hay 4.6 GB. Se repite después de la ampliación del disco (§6).

## 4. Incidente: fallo en la restauración del respaldo verificado

- **Síntoma:** `pg_restore: … FATAL: database "bd_restaurada_desechable" does not exist` (respaldo `…_20261126_184153`, conservado como evidencia del intento fallido).
- **Causa:** durante la inicialización, la imagen `postgres:18.6` arranca un servidor temporal **solo por socket**. `pg_isready` respondía «listo» antes de crear `POSTGRES_DB`.
- **Corrección (`8042dee`):** se espera por TCP (`-h 127.0.0.1`; el servidor temporal no escucha ahí) y hasta que la base acepte `select 1`, con un límite de 60 s y detención explícita.
- **Resultado con el script corregido:** `respaldo_verificado_bd_anfeca_ensayo_pruebas_pg_20261126_184243`:
  - `pg_restore` correcto;
  - inventario idéntico (1 513 elementos);
  - conteos y MD5 idénticos en 75 tablas y 184 filas;
  - `migrate:status`: 35 ejecutadas y 1 pendiente (la ajena `escuela_facultad`).
- **Pendiente:** `probar_migracion_catalogo.sh` usa la misma espera por socket, mitigada con `sleep 2`. Conviene aplicarle la misma corrección.

## 5. Despliegue controlado de Formación Académica en 8086 (preparado; NO ejecutado)

**Alcance autorizable por separado:**
- (A) código y compilación con las banderas **desactivadas**: sin efecto funcional; la pantalla sigue igual;
- (B) migración aditiva (`LIC-03`);
- (C) bandera `ANFECA_DECLARACION_POSGRADO`;
- (D) bandera `ANFECA_CATALOGO_LICENCIATURAS`, que depende de la clasificación (LIC-01) y de D-LIC-1.

**Condiciones previas:**
1. Disco ampliado (§6) y ≥ 5 GB libres.
2. `probar_migracion_licenciaturas.sh` 18/18 sobre `revision/cal-01-formacion`.
3. Respaldo verificado con menos de 2 h (`respaldo_verificado.sh`, ya corregido).
4. Las 20 huellas documentales idénticas a la línea base. Hoy: 20/20 idénticas, todas con HTTP 200.

**(A) Código y compilación:**

```sh
cd /var/www/ANFECA_ENSAYO
git fetch github
git merge --ff-only github/revision/cal-01-formacion        # los cambios locales no se tocan (sin solape)
php artisan config:clear && php artisan view:clear
# Compilación en árbol aislado y despliegue atómico del manifiesto:
bash scripts/anfeca/pruebas/arbol_aislado.sh "$HOME/compilacion_lic_$(date +%H%M)" HEAD
(cd "$HOME/compilacion_lic_"* && npm run build)
bash scripts/anfeca/desplegar_compilacion.sh desplegar "$HOME"/compilacion_lic_*/public/build "$HOME/ANFECA_RESPALDOS/build_antes_lic_$(date +%Y%m%d_%H%M)"
# Verificación: /registro/formacion-academica 200 con data-catalogo-licenciaturas {"estado":"desactivado"} y sin casilla
bash scripts/anfeca/huellas_documentos.sh http://192.168.7.130:8086 "$HOME/ANFECA_EVIDENCIAS/huellas_post_despliegue.json"
```

- OPcache revalida por marca de tiempo (valores por omisión) y la configuración no está en caché: no se requiere recargar PHP-FPM.
- **Reversión de (A):**
  - `bash scripts/anfeca/desplegar_compilacion.sh restaurar <manifest anterior>`;
  - `git revert --no-edit 44f522a..HEAD` (nunca `reset --hard`).

**(B) Migración** (solo con autorización): según `aplicar_licenciaturas_posgrado.sh`, con `ANFECA_AUTORIZO_MIGRACION=licenciaturas_posgrado` y el respaldo verificado del mismo commit.

## 6. Ampliación del almacenamiento (preparada; requiere sudo)

**Diagnóstico** (sin root, desde `/sys`; sectores de 512 B):

| Elemento | Valor |
|---|---|
| Disco | 104 857 600 sectores = **50 GiB** (el kernel ya lo detecta) |
| p1 / p2 | 1 MiB / 2 GiB (`/boot`) |
| p3 | inicio 4 198 400, 37 742 592 sectores (18 GiB). **Última** partición |
| root / swap | 16 GiB XFS (`reflink=1`) / 2 GiB (166 MiB en uso) |
| Libre en `/` | 4.6 GB (72 %) |
| Herramientas | `parted` 3.6, `lvm` y `xfs_growfs`; faltan `growpart` (`cloud-utils-growpart`) y `gdisk` |
| ISO | `sr0` montada en la sesión GNOME |

**Simulación (cálculo):**
- último sector utilizable de GPT: 104 857 566, así que p3 ≈ 49 150 MiB;
- VG ≈ 49 148 MiB: root **44 GiB** (45 056 MiB), swap **2 GiB** sin cambios y **reserva ≈ 2 040 MiB**.

**Script** `scripts/anfeca/ampliar_almacenamiento.sh` (`3fe6cfb`):
- `diagnosticar` y `simular`: sin escrituras (`growpart --dry-run`, `pvresize`/`lvextend --test`);
- `aplicar`:
  - exige la huella SHA-256 de la copia externa calculada **fuera** de la VM y la confirmación del respaldo completo de la VM;
  - respalda `sfdisk -d` y `vgcfgbackup`;
  - ejecuta `growpart` → `pvresize` → `lvextend -L 44G` → `xfs_growfs /`;
  - verifica swap = 2 GiB, reserva ≥ 1.5 GiB y `/` ≥ 44 GiB.

**Copia externa:** `~/ANFECA_COPIA_EXTERNA_20261126/anfeca_copia_externa_20261126.tar` (82 MB, 0600), SHA-256 `9ba601d0434cb47e6f491321b7cbc3cc53cc61eadcb0dd890cf94bc8b7e06c00`. Contiene:
- bundle de git con todas las ramas;
- parche de los cambios locales y archivos sin rastrear;
- `.env` de 8086;
- `ANFECA_RESPALDOS`, `ANFECA_EVIDENCIAS`, `ANFECA_FUENTES` y `ANFECA_CREDENCIALES`;
- `storage/app`.

Es **parcial sin sudo**:
- faltan `storage/app/private/anfeca` (los documentos de los folios, propiedad de apache);
- falta `ANFECA_RESPALDOS/ANFECA_PRUEBAS_20261119_100334/proyecto_completo.tar.gz`.

El complemento con sudo está en la lista de comandos del responsable.

**XFS no se reduce:** la reversión de la ampliación es restaurar la VM.

## 7. Coordinación (solo lectura; sin cambios)

| Comprobación (8086, transacción de solo lectura) | Resultado |
|---|---|
| `zonas` | 0 filas |
| `instituciones_afiliadas` | id 1 «Zona 6 - Sur», id 2 «Zona 4 - Centro Occidente», id 3 «Zona 3 - Centro»; las 3 sin `zona_id` |
| `coordinador_zona` | 0 filas; 3 cuentas `coordinador_zona` y 1 `coordinador_nacional` |
| `verificar_revisiones_documentales.sh` | 0 fallas (incluye la migración ajena sin aplicar) |
| 20 huellas documentales | 20/20 idénticas a `documentos_huellas_antes.json` del 25-nov |
| Pruebas de permisos (`CoordinacionTest`, `RevisionDocumentalTest`, `ZonasAnfecaTest`, esquema) | 68 correctas y 1 omitida |

Conclusión:
- El SQL de `ZONAS_DIAGNOSTICO_Y_NORMALIZACION.md` §4 y §5 **sigue vigente sin cambios** (se esperan exactamente 3 filas).
- Las condiciones del §13 de `PERSISTENCIA_REVISION_DOCUMENTAL_FASE_6_3_1.md` para activar la revisión documental se cumplen.
- Orden recomendado: respaldo verificado → ZON-01 → ZON-02 (cuentas de prueba) → REV-01.

## 8. Siguiente fase: administración de convocatorias (propuesta para decisión)

**Estado:**
- Laravel no tiene una entidad de convocatoria: `solicitudes_certificacion` no tiene `convocatoria_id`, y el acuse solo menciona «la convocatoria» en texto fijo.
- El PDM (apagado) tiene `anfeca_modelo.convocatoria` (0 filas) y `tipo_convocatoria`, con nombre, monto de recuperación, fechas de registro, resultados y entrega, y puntuación máxima y mínima. `solicitud.soli_id_convocatoria` se mapea hoy desde `certificacion`.

**Propuesta mínima (sin implementar):**
1. Tabla aditiva `convocatorias`, alineada con el PDM: `tipo` (ANFECA, CACECA o CACSLA), `nombre`, `anio`, `fecha_inicio_registro`, `fecha_fin_registro`, `fecha_resultados`, `monto_recuperacion`, `estado` (borrador, publicada o cerrada) y la referencia al documento oficial.
2. `solicitudes_certificacion.convocatoria_id` nullable. Las solicitudes existentes quedan NULL, o se asignan a «Convocatoria 2026» con autorización.
3. Validar la ventana de registro al **crear** la solicitud. Las solicitudes en curso no se bloquean.
4. CRUD para `coordinador_nacional`, con bitácora. El regional solo consulta.
5. Primero, la extensión de `anfeca_compat` (como CAT-09), antes de cualquier activación del PDM.

**Decisiones requeridas:**
- numeración de la fase (6.3.4 frente a 6.3.6, ver el inventario);
- datos oficiales de la convocatoria 2027;
- comportamiento fuera de la ventana de registro;
- si las solicitudes 2026 se asignan retroactivamente.
