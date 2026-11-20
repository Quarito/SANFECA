# Incidencias bloqueantes del plan F2 (ensayo del 20/11/2026)

**Diagnóstico de solo lectura**, sin datos personales:

```bash
DB_DATABASE=bd_anfeca_ensayo_pruebas_pg php scripts/anfeca/diagnosticar_incidencias.php <plan_f2.json>
```

| Código | IDs | Causa (regla) | Solución propuesta | Fuente | ¿Autorización humana? |
|---|---|---|---|---|---|
| D-11 `INSTITUCION_SIN_DATOS_OFICIALES` | 1 | Afiliación nula: `INST_NUMERO_AFILIACION` (≤7) y la facultad son obligatorias en el PDM. | Registrar el número de afiliación y la facultad oficiales. `no_migrar` desbloquea F2/espejo, pero deja a las personas sin institución en el modo principal. | Padrón oficial ANFECA. | **Sí** (dato institucional). |
| D-13 `ENTIDAD_EN_REVISION` | 5 de 7 | `Entidad de Prueba`: valor de prueba, regla N-3 (`no_entidades`). | Corregir la entidad desde la aplicación con el dato real del solicitante (antes de autorizar sus datos) o con la confirmación de la persona. Si son registros de prueba, `sin_domicilio` (public los conserva). No se eliminan. | Confirmación de la persona, o constancia de que son registros de prueba. | **Sí**. |
| D-13 `ENTIDAD_EN_REVISION` | 2 de 7 | `Monterrey`: es un municipio, no una entidad (N-3). | `['entidad' => 'Nuevo León', 'fuente' => …]` **solo si** el diagnóstico confirma municipio Monterrey y CP 64000–67999. | INEGI (único municipio Monterrey) + SEPOMEX (rango de CP) + el propio registro. | **Sí**: la regla acordada exige confirmación; la evidencia técnica existe. |
| D-14 `CERTIFICADO_EN_REVISION` | 1, 2, 3, 6, 7, 9, 10, 11 | El número no cumple `^[0-9]{1,7}$` (N-5). Los años son válidos (consulta F). | `conservar_en_revision`: copia íntegra en `HISTORICO_CERTIFICADO_IMPORTACION`, sin eventos normalizados y sin cambiar nada visible. Si el diagnóstico muestra `decimal de Excel (.0)`, se puede registrar `['numero' => '<sin .0>', 'fuente' => 'Artefacto de exportación de Excel: «<valor>» = <número>']`. | Archivo histórico original (Excel), regla N-5. | **Sí** (decisión D-14 por registro). |
| D-15 `SOLICITUD_INVALIDA` | 4 | Área y tipo de personal vacíos (consulta D: 1 y 1). N-6 no aplica porque el usuario no tiene selección con la misma certificación. | `['area_certificacion' => …, 'tipo_personal' => …, 'fuente' => …]` con valores del catálogo. | Expediente o acuse de la solicitud, o confirmación del solicitante. | **Sí**. |

**Plantilla** para `config/anfeca_pdm_decisiones.php`. Las decisiones se registran **solo en el servidor** y nunca se suben a GitHub; los IDs no son datos personales.

```php
'instituciones' => [1 => ['numero_afiliacion' => '…', 'facultad' => '…', 'fuente' => 'Padrón ANFECA …']],
'domicilios'    => [/* id => ['entidad' => 'Nuevo León', 'fuente' => 'INEGI+SEPOMEX: municipio Monterrey, CP 64xxx'] | 'sin_domicilio' */],
'certificados'  => [1 => 'conservar_en_revision', 2 => 'conservar_en_revision', /* … */],
'solicitudes'   => [4 => ['area_certificacion' => '…', 'tipo_personal' => '…', 'fuente' => '…']],
```
