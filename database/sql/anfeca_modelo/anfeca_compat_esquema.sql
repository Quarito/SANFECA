-- =====================================================================
-- ANFECA — Capa de COMPATIBILIDAD (F4): vistas con la forma de las tablas de public
-- y los datos de anfeca_modelo (PowerDesigner).
--
-- * Esquema técnico anfeca_compat (no forma parte del PDM).
-- * Cada vista tiene exactamente el nombre y las columnas de la tabla Laravel que sustituye,
--   para que modelos Eloquent, validaciones, Blade y Vue funcionen sin cambios cuando la
--   conexión usa search_path = anfeca_compat, public (modos principal y exclusivo).
-- * Una sola tabla base en FROM (lo demás son subconsultas escalares): así
--   SELECT ... FOR UPDATE (lockForUpdate) sigue funcionando sobre las vistas.
-- * Fechas y horas locales del PDM (America/Mexico_City) se devuelven en UTC, como Laravel.
-- =====================================================================

create schema anfeca_compat;

-- ---------------------------------------------------------------------
-- Tablas técnicas de correspondencia (se llenan desde config/anfeca_pdm.php al migrar)
-- ---------------------------------------------------------------------
create table anfeca_compat.mapa_documento (campo varchar(60) primary key, descripcion varchar(150) not null);
create table anfeca_compat.mapa_comunicacion (campo varchar(60) primary key, descripcion varchar(50) not null);
create table anfeca_compat.mapa_estado_solicitud (siglas varchar(2) primary key, descripcion varchar(50) not null);
create table anfeca_compat.alias_entidad (clave varchar(100) primary key, entidad varchar(85) null, motivo varchar(300) null);
create table anfeca_compat.parametro (clave varchar(60) primary key, valor varchar(100) not null);
