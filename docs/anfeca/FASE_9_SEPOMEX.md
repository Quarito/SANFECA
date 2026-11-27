# Fase 9 — SEPOMEX (borrador de integración)

Base: 6c7328686be6fc736f7dec5e44ae20eef24c5aa8. No fusionar ni desplegar hasta validar.

Fuente: https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx
Descargar TXT nacional, descomprimir fuera de public y conservar original, fecha y SHA-256.

## Diagnóstico
En una copia independiente de la aplicación, conectada a una copia restaurada de PostgreSQL:
```sh
php artisan anfeca:verificar-esquema
php -l app/Console/Commands/ImportarSepomex.php
php artisan help anfeca:sepomex
sha256sum storage/app/private/catalogos/sepomex/CPdescarga.txt
php artisan anfeca:sepomex storage/app/private/catalogos/sepomex/CPdescarga.txt
```
La simulación usa transacción de solo lectura. Requiere las 32 entidades previamente conciliadas con el catálogo oficial existente. No ejecutar el sembrador general sin revisión. Si hay nombres ambiguos, duplicados, claves inesperadas o asentamientos indistinguibles, detenerse y resolver con el TXT original; no eliminar ni fusionar silenciosamente.

## Aplicación (solo en copia restaurada autorizada)
Tras comprobar respaldo restaurado, esquema, secuencias SERIAL, versión del archivo y resultado de diagnóstico, ejecutar:
```sh
php artisan anfeca:sepomex storage/app/private/catalogos/sepomex/CPdescarga.txt --aplicar --base=NOMBRE_EXACTO_COPIA --sha256=HUELLA_VERIFICADA
```
No ejecutar en 8008, 8086, 8088 ni producción sin autorización específica.
Repetir diagnóstico: cero faltantes. Comparar conteos y huellas de filas existentes antes/después, verificar las 36 tablas y las FK. No modificar domicilio, comunicación, tipo_comunicacion ni sesiones.

## Persistencia pendiente
El esquema oficial relaciona domicilio con entidad, no colonia. config/anfeca_8088.php contempla CODIGO_POSTAL, COLONIA, MUNICIPIO mediante tipos de comunicación. Revisar su implementación antes de conectar Vue; conservar el mecanismo existente hasta autorización del cambio físico.

## Pruebas pendientes
TXT nacional real; UTF-8 y Windows-1252; CP inicial cero; archivo incompleto; duplicados y colisiones; entidad inconsistente; hash incorrecto; base incorrecta; transacción ante fallo; reejecución idempotente; conteos y rendimiento; comparación contra SEPOMEX; bloqueo de datos autorizados; primera vez y refrendo. No se afirma que estas pruebas hayan sido ejecutadas.
