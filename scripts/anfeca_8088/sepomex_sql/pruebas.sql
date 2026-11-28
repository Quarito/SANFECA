-- =====================================================================================================================
-- ANFECA · Preparación de los escenarios de prueba de los scripts SQL SEPOMEX (solo bases NUEVAS y desechables).
-- Uso: psql -v ON_ERROR_STOP=1 -v escenario=<e2|e3|e4|e5a|e5b|e5c|e5d|e8|entidades> -d <base de prueba> -f pruebas.sql
-- Contiene únicamente nombres de entidades y unos cuantos municipios y colonias de ejemplo, y registros FICTICIOS.
-- =====================================================================================================================
\set ON_ERROR_STOP on
SET client_encoding = 'UTF8';
SELECT :'escenario' IN ('e2') AS e2, :'escenario' IN ('e3', 'e5a', 'e5c', 'entidades') AS exactas,
       :'escenario' = 'e3' AS e3, :'escenario' = 'e4' AS e4, :'escenario' = 'e5a' AS e5a, :'escenario' = 'e5b' AS e5b,
       :'escenario' = 'e5c' AS e5c, :'escenario' = 'e5d' AS e5d, :'escenario' = 'e8' AS e8 \gset
BEGIN;

\if :e2
-- 32 entidades con grafía distinta (mayúsculas, sin acentos), identificadores 1–32: deben reutilizarse sin modificarse.
INSERT INTO entidad_federativa (enfe_id_entidad_federativa, enfe_nombre) VALUES
 (1,'AGUASCALIENTES'),(2,'BAJA CALIFORNIA'),(3,'BAJA CALIFORNIA SUR'),(4,'CAMPECHE'),(5,'COAHUILA DE ZARAGOZA'),(6,'COLIMA'),
 (7,'CHIAPAS'),(8,'CHIHUAHUA'),(9,'CIUDAD DE MEXICO'),(10,'DURANGO'),(11,'GUANAJUATO'),(12,'GUERRERO'),(13,'HIDALGO'),
 (14,'JALISCO'),(15,'MEXICO'),(16,'MICHOACAN DE OCAMPO'),(17,'MORELOS'),(18,'NAYARIT'),(19,'NUEVO LEON'),(20,'OAXACA'),
 (21,'PUEBLA'),(22,'QUERETARO'),(23,'QUINTANA ROO'),(24,'SAN LUIS POTOSI'),(25,'SINALOA'),(26,'SONORA'),(27,'TABASCO'),
 (28,'TAMAULIPAS'),(29,'TLAXCALA'),(30,'VERACRUZ DE IGNACIO DE LA LLAVE'),(31,'YUCATAN'),(32,'ZACATECAS');
SELECT setval('entidad_federativa_enfe_id_entidad_federativa_seq', 32);
\endif

\if :exactas
INSERT INTO entidad_federativa (enfe_id_entidad_federativa, enfe_nombre) VALUES
 (1,'Aguascalientes'),(2,'Baja California'),(3,'Baja California Sur'),(4,'Campeche'),(5,'Coahuila de Zaragoza'),(6,'Colima'),
 (7,'Chiapas'),(8,'Chihuahua'),(9,'Ciudad de México'),(10,'Durango'),(11,'Guanajuato'),(12,'Guerrero'),(13,'Hidalgo'),
 (14,'Jalisco'),(15,'México'),(16,'Michoacán de Ocampo'),(17,'Morelos'),(18,'Nayarit'),(19,'Nuevo León'),(20,'Oaxaca'),
 (21,'Puebla'),(22,'Querétaro'),(23,'Quintana Roo'),(24,'San Luis Potosí'),(25,'Sinaloa'),(26,'Sonora'),(27,'Tabasco'),
 (28,'Tamaulipas'),(29,'Tlaxcala'),(30,'Veracruz de Ignacio de la Llave'),(31,'Yucatán'),(32,'Zacatecas');
SELECT setval('entidad_federativa_enfe_id_entidad_federativa_seq', 32);
\endif

\if :e3
-- Municipios y colonias previos (tres oficiales y una colonia ficticia ajena al catálogo).
INSERT INTO alcaldia_municipio (almu_id_entidad_federativa, almu_nombre) VALUES (14, 'Guadalajara'), (9, 'Coyoacán'), (20, 'Oaxaca de Juárez');
INSERT INTO colonia (colo_id_alcaldia, colo_nombre, colo_codigo_postal) VALUES
 (1, 'Arcos', '44130'), (2, 'Universidad Nacional Autónoma de México', '04510'), (1, 'Colonia Ficticia de Prueba', '44999');
\endif

\if :e4
-- Identificadores distintos a los del laboratorio: 16 entidades con ids desplazados (Aguascalientes = 17 … Michoacán = 32),
-- secuencias atrasadas y municipios/colonias con identificadores explícitos que colisionarían sin ajustar la secuencia.
INSERT INTO entidad_federativa (enfe_id_entidad_federativa, enfe_nombre) VALUES
 (17,'Aguascalientes'),(18,'Baja California'),(19,'Baja California Sur'),(20,'Campeche'),(21,'Coahuila de Zaragoza'),(22,'Colima'),
 (23,'Chiapas'),(24,'Chihuahua'),(25,'Ciudad de México'),(26,'Durango'),(27,'Guanajuato'),(28,'Guerrero'),(29,'Hidalgo'),
 (30,'Jalisco'),(31,'México'),(32,'Michoacán de Ocampo');
INSERT INTO alcaldia_municipio (almu_id_alcaldia, almu_id_entidad_federativa, almu_nombre) VALUES
 (1, 30, 'Zapopan'), (2, 30, 'San Pedro Tlaquepaque'), (90000, 30, 'Guadalajara');
INSERT INTO colonia (colo_id_colonia, colo_id_alcaldia, colo_nombre, colo_codigo_postal) VALUES (1, 1, 'Colonia Ficticia de Prueba', '45999');
\endif

\if :e5a
INSERT INTO alcaldia_municipio (almu_id_entidad_federativa, almu_nombre) VALUES (14, 'Guadalajara'), (14, 'Guadalajara');
\endif

\if :e5b
-- La clave oficial 09 la ocupa un nombre ajeno al catálogo y «Ciudad de México» no existe: requiere revisión.
INSERT INTO entidad_federativa (enfe_id_entidad_federativa, enfe_nombre) VALUES (9, 'Entidad Ficticia de Prueba');
\endif

\if :e5c
-- Grafía distinta de un municipio existente: la carga no debe duplicarlo ni renombrarlo; se detiene.
INSERT INTO alcaldia_municipio (almu_id_entidad_federativa, almu_nombre) VALUES (14, 'GUADALAJARA');
\endif

\if :e5d
-- Entidad registrada dos veces (coincidencia ambigua).
INSERT INTO entidad_federativa (enfe_id_entidad_federativa, enfe_nombre) VALUES (14, 'Jalisco'), (40, 'JALISCO');
\endif

\if :e8
-- Solicitantes FICTICIOS con domicilio, comunicación y usuario (requiere las entidades y los catálogos de ANFECA).
INSERT INTO persona (pers_nombre, pers_apellido_paterno, pers_apellido_materno, pers_curp, pers_rfc, pers_genero, pers_activo) VALUES
 ('Ana Ficticia', 'Prueba', 'Uno', 'FIPA800101MDFCRN01', 'FIPA800101AB1', 'M', true),
 ('Luis Ficticio', 'Prueba', 'Dos', 'FIPL800202HJCCRS02', 'FIPL800202AB2', 'H', true),
 ('Eva Ficticia', 'Prueba', 'Tres', 'FIPE800303MYNCRV03', 'FIPE800303AB3', 'M', true);
INSERT INTO domicilio (domi_id_persona, domi_id_entidad_federativa, domi_calle, domi_numero_ext, domi_numero_int)
SELECT p.pers_id_persona, e.enfe_id_entidad_federativa, 'Calle Ficticia ' || p.pers_id_persona, '10', NULL
  FROM persona p JOIN entidad_federativa e ON e.enfe_nombre = CASE p.pers_curp WHEN 'FIPA800101MDFCRN01' THEN 'Ciudad de México'
       WHEN 'FIPL800202HJCCRS02' THEN 'Jalisco' ELSE 'Yucatán' END
 WHERE p.pers_curp LIKE 'FIP%';
INSERT INTO comunicacion (comu_id_persona, comu_id_tipo_comunicacion, comu_valor, comu_visible)
SELECT p.pers_id_persona, t.tico_id_tipo_comunicacion, lower(split_part(p.pers_nombre, ' ', 1)) || '.ficticia@example.test', true
  FROM persona p CROSS JOIN (SELECT min(tico_id_tipo_comunicacion) AS tico_id_tipo_comunicacion FROM tipo_comunicacion) t
 WHERE p.pers_curp LIKE 'FIP%' AND t.tico_id_tipo_comunicacion IS NOT NULL;
INSERT INTO usuario (usua_id_persona, usua_id_rol, usua_nombre, usua_contrasenia, usua_estatus)
SELECT p.pers_id_persona, r.rol_id_rol, lower(split_part(p.pers_nombre, ' ', 1)) || '.ficticia@example.test', 'hash-ficticio-no-valido', 'ACTIVO'
  FROM persona p CROSS JOIN (SELECT min(rol_id_rol) AS rol_id_rol FROM rol) r
 WHERE p.pers_curp LIKE 'FIP%' AND r.rol_id_rol IS NOT NULL;
\endif

COMMIT;
