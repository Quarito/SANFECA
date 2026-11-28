# Fase 9 · Catálogo SEPOMEX y domicilio en el modelo vigente

Rama `desarrollo/catalogo-sepomex` (incluye las mejoras de sesión de `feature/control-sesion-8088`). Fuente de verdad: el
modelo oficial PowerDesigner de 36 tablas, la estructura real de PostgreSQL y los requerimientos aprobados. **El modelo físico
no cambia**: no se crea `DOMICILIO.DOMI_ID_COLONIA` (decisión definitiva) ni ninguna otra estructura.

## 1. Estructura vigente comprobada

| Tabla | Atributos | Relación |
|---|---|---|
| `ENTIDAD_FEDERATIVA` | `ENFE_ID_ENTIDAD_FEDERATIVA` (SERIAL), `ENFE_NOMBRE` (85) | — |
| `ALCALDIA_MUNICIPIO` | `ALMU_ID_ALCALDIA`, `ALMU_ID_ENTIDAD_FEDERATIVA`, `ALMU_NOMBRE` (150) | `FK_ALCALDIA_CONTIENE_ENTIDAD_` → entidad |
| `COLONIA` | `COLO_ID_COLONIA`, `COLO_ID_ALCALDIA`, `COLO_NOMBRE` (150), `COLO_CODIGO_POSTAL` (5) | `FK_COLONIA_SE_COMPON_ALCALDIA` → alcaldía/municipio |
| `DOMICILIO` | `DOMI_ID_DOMICILIO`, `DOMI_ID_PERSONA`, `DOMI_ID_ENTIDAD_FEDERATIVA`, `DOMI_CALLE` (170), `DOMI_NUMERO_EXT` (6), `DOMI_NUMERO_INT` (4) | → `PERSONA`, → `ENTIDAD_FEDERATIVA` |

Comprobado contra `database/sql/oficial/…sql` y con `php artisan anfeca:verificar-esquema` (36/36 tablas, sin diferencias).
En el servidor 8088 se confirma, en solo lectura, con:

```bash
cd /var/www/ANFECA_8088 && sudo -u apache php artisan anfeca:verificar-esquema
```

## 2. Importación del catálogo nacional

- Fuente: Catálogo Nacional de Códigos Postales de Correos de México (XLS `CPdescarga.xls` o TXT `CPdescarga.txt`). Archivo
  recibido: versión **21/11/2026**, `CPdescarga.xls` SHA-256 `0a8e2611b92635f47c646816b3641354ab4a554f64b058447890d77e8a052821`.
  **No se versiona**: su nota prohíbe la distribución a terceros. Se guarda fuera de `public/` (p. ej.
  `/var/lib/anfeca8088/sepomex/`).
- Comando `php artisan anfeca:sepomex <archivo>` (`App\Console\Commands\ImportarSepomex` + `App\Services\Anfeca\Sepomex\LectorSepomex`):
  - Sin opciones: **diagnóstico** en una transacción de solo lectura.
  - `--aplicar --base=<base> --sha256=<huella>`: inserta **solo lo faltante**, en una transacción con bloqueo de las tres tablas;
    nunca actualiza ni elimina, ni toca `DOMICILIO` u otras tablas. Reejecutarlo no inserta nada (idempotente).
  - Mapeo: `c_estado`/`d_estado` → `ENTIDAD_FEDERATIVA` (clave = id); `D_mnpio` → `ALCALDIA_MUNICIPIO`; `d_asenta` + `d_codigo` → `COLONIA`.
  - Validaciones: encabezado oficial, claves numéricas (CP de 5 dígitos con ceros iniciales), longitudes del modelo, una clave
    por nombre de entidad y de municipio, y conciliación con las entidades ya existentes (si un nombre difiere, se detiene).
  - `COLONIA` no tiene tipo de asentamiento: los registros que solo difieren en ese dato se guardan **una vez** (sin duplicar).
  - Uso de memoria: ~311 MB; el comando eleva `memory_limit` a 1024 M; duración ~2 min 45 s.
- `anfeca:catalogos` ya no carga entidades: la geografía proviene exclusivamente de SEPOMEX. La configuración anterior
  (lista y alias de entidades) se retiró.

### 2.1 Validación del catálogo (base nueva y aislada)

| Concepto | Resultado |
|---|---|
| Versión SEPOMEX | 21/11/2026 |
| Archivo original | `CPdescarga.xls`, SHA-256 `0a8e2611b92635f47c646816b3641354ab4a554f64b058447890d77e8a052821` (ZIP recibido: `ac8fbc117d3737e20730687cdc545d8e92191436bcd34c6e3a16de15acc792d3`) |
| Filas leídas | 159 527 (33 hojas: «Nota» + 32 entidades) |
| Entidades federativas | **32** (claves 01–32; nombres idénticos a los de INEGI) |
| Alcaldías y municipios | **2 478** |
| Colonias | **158 507** · 31 860 códigos postales (685 con cero inicial) · cada CP en un solo municipio y una sola entidad |
| Consolidados por tipo de asentamiento | **1 020** registros en 939 colonias (misma entidad, municipio, CP y nombre; `COLONIA` no tiene tipo) |
| Variantes de acentos o mayúsculas | 14 nombres (se conservan tal cual) |
| Tiempo de procesamiento | 166.8–169 s (lectura del XLS ~160 s; inserción en una transacción) |
| Memoria pico | 515 MB del proceso PHP (`memory_limit` 1024 M) |
| Idempotencia | 2.ª ejecución con `--aplicar` y diagnósticos posteriores: 0 entidades, 0 municipios, 0 colonias pendientes |
| Integridad | Sin colonias ni municipios huérfanos, sin duplicados exactos; esquema 36/36 sin diferencias |

Reporte de consolidación: `php artisan anfeca:sepomex <archivo> --reporte=<ruta privada>.csv` (entidad, municipio, CP,
colonia, tipos de origen). No se versiona porque reproduce contenido del catálogo. Distribución por entidad:

| Entidad | Colonias | Registros | Entidad | Colonias | Registros |
|---|---|---|---|---|---|
| Chihuahua | 234 | 279 | Tabasco | 14 | 14 |
| Sonora | 132 | 147 | Tamaulipas | 14 | 14 |
| Durango | 77 | 86 | Baja California | 13 | 13 |
| Chiapas | 62 | 65 | Morelos | 9 | 10 |
| Guanajuato | 40 | 40 | Nayarit | 9 | 10 |
| Coahuila de Zaragoza | 35 | 36 | Aguascalientes | 8 | 8 |
| Hidalgo | 35 | 35 | Tlaxcala | 8 | 8 |
| Veracruz de Ignacio de la Llave | 33 | 34 | Guerrero | 7 | 7 |
| San Luis Potosí | 29 | 31 | Sinaloa | 7 | 7 |
| Michoacán de Ocampo | 29 | 30 | Baja California Sur | 5 | 5 |
| México | 29 | 29 | Campeche | 5 | 5 |
| Puebla | 26 | 26 | Yucatán | 5 | 5 |
| Nuevo León | 19 | 20 | Ciudad de México | 4 | 4 |
| Jalisco | 17 | 17 | Zacatecas | 3 | 3 |
| Querétaro | 14 | 15 | Quintana Roo | 2 | 2 |
| Oaxaca | 14 | 14 | Colima | 1 | 1 |

Tipos de origen más frecuentes en los consolidados: Ranchería, Ejido, Colonia, Rancho, Fraccionamiento, Pueblo, Barrio.

El archivo original se conserva en almacenamiento privado (fuera de GitHub y de `public/`), conforme a la nota de
Correos de México que prohíbe su distribución a terceros.

## 3. Consulta por código postal

`GET /catalogos/codigo-postal/{cp}` (`App\Services\Anfeca\Sepomex\ConsultaCodigoPostal`; visitantes y solicitantes; 60/min).
Lee `COLONIA → ALCALDIA_MUNICIPIO → ENTIDAD_FEDERATIVA` directamente de PostgreSQL y devuelve el municipio, su entidad y sus
colonias; 404 si el código no existe. Tiempo medido ~25 ms (no hay índice sobre `COLO_CODIGO_POSTAL`; no se propone ninguno).

En las pantallas de registro y modificación (mismos campos y distribución), al completar el código postal se llenan entidad y
alcaldía o municipio, y las colonias del código se ofrecen como sugerencias del campo «Colonia»
(`resources/js/composables/codigoPostal.js`). Un código inexistente impide enviar con el mensaje nativo del navegador.

## 4. Validación: captura, persistencia y autorización

| Etapa | Qué se valida | Dónde |
|---|---|---|
| **Captura** (alta y modificación antes de autorizar) | Código postal existente; alcaldía o municipio, entidad federativa y colonia que **correspondan** a ese código | `ValidacionDomicilio` (servidor), además de la ayuda del navegador |
| **Persistencia** | `DOMI_CALLE` (≤ 170), `DOMI_NUMERO_EXT` (≤ 6), `DOMI_NUMERO_INT` (≤ 4, opcional) y `DOMI_ID_ENTIDAD_FEDERATIVA` **derivada del código postal validado** | `ExpedienteSolicitante` |
| **Autorización** | CURP, RFC, nombre, apellido paterno, calle, número exterior, entidad federativa, correo y al menos un teléfono: solo atributos que el modelo guarda | `DatosSolicitanteController::autorizar` |

Código postal, colonia y municipio **no** se exigen al autorizar porque no existen como atributos persistidos. No se guardan en
`COMUNICACION`, `TIPO_COMUNICACION`, sesiones, archivos JSON ni en ningún otro lugar.

## 5. Información geográfica recuperable después de guardar

| Dato | ¿Recuperable? | Motivo |
|---|---|---|
| Calle, número exterior, número interior | Sí | Atributos de `DOMICILIO` |
| Entidad federativa | Sí | `DOMICILIO.DOMI_ID_ENTIDAD_FEDERATIVA → ENTIDAD_FEDERATIVA` |
| Alcaldía o municipio | **No** | `DOMICILIO` no se relaciona con `ALCALDIA_MUNICIPIO`; la entidad no basta para determinarlo |
| Colonia | **No** | `DOMICILIO` no se relaciona con `COLONIA` |
| Código postal | **No** | Atributo de `COLONIA`, que no se relaciona con `DOMICILIO` |

Presentación (ajuste aprobado):
- **Mis datos (consulta y autorización)**: la sección Domicilio muestra solo calle, número exterior, número interior y entidad
  federativa. Código postal, colonia y municipio no se presentan.
- **Modificación antes de autorizar**: se conservan los datos guardados y aparecen de nuevo código postal, colonia y municipio
  para capturarlos, con la indicación «Ingrese nuevamente su código postal para validar los datos de su domicilio.»
- **Después de autorizar**: sin opción de modificar (bloqueo existente).
- **Colonia**: sugerencias exclusivamente del código postal ingresado; se puede buscar (sin distinguir acentos ni mayúsculas;
  se adopta la escritura oficial), y un valor fuera del catálogo impide enviar. El servidor también lo rechaza.
- **Coordinación y acuse PDF**: no presentan el domicilio (revisado en el código).

## 6. Pruebas (entorno aislado: instancia PostgreSQL 18.6 desechable y base nueva con datos ficticios)

| Suite | Resultado |
|---|---|
| Regresión completa `e2e_8088.py` (incluye captura válida e inválida, no persistencia geográfica, autorización y refrendo) | 30/30 |
| Catálogos de 8086 `e2e_catalogos_8088.py` | 4/4 |
| Código postal y presentación del domicilio en navegador `prueba_codigo_postal_navegador.cjs` | 12/12 |
| Control de sesión: servidor / navegador | 22/22 · 13/13 |
| Importación: diagnóstico, aplicación, reejecución, huella incorrecta, base incorrecta, integridad | Aprobadas |

Total con `scripts/anfeca_8088/pruebas/ejecutar_bateria.sh`: **81/81** (76 previas + 5 nuevas de presentación y colonia).
Evidencias: `docs/anfeca/evidencias_8088/sepomex/` y `docs/anfeca/evidencias_8088/fase9/`.

## 7. Aplicación en 8088

Procedimiento completo por bloques en `docs/anfeca/OPERACION_8088_FASE9.md` (rama `integracion/fase9-8088`). Resumen:

1. Copiar el archivo oficial a `/var/lib/anfeca8088/sepomex/` y anotar su `sha256sum`.
2. Respaldo verificado: `scripts/anfeca_8088/servidor/respaldo_8088.sh`.
3. Desplegar la rama, `php artisan config:cache` y `php artisan view:clear`; compilar el frontend (`npm ci && npm run build`).
4. `php -d memory_limit=1024M artisan anfeca:sepomex <archivo>` (diagnóstico) y, si no hay incidencias,
   `… --aplicar --base=certificacion_anfeca --sha256=<huella>`; repetir el diagnóstico (0 faltantes).
5. `php artisan anfeca:verificar-esquema` y las pruebas de `scripts/anfeca_8088/pruebas/` con datos ficticios.
