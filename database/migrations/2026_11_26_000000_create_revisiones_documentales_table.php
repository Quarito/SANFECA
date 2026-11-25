<?php

/*
|--------------------------------------------------------------------------
| AUTORIZADA — Fase 6.3.1 · public.revisiones_documentales
|--------------------------------------------------------------------------
| Incorporada a database/migrations por autorización expresa (2026-11-25). En el ambiente de
| ensayo se aplica EXCLUSIVAMENTE con:
|   php artisan migrate --path=database/migrations/2026_11_26_000000_create_revisiones_documentales_table.php --force
| Nunca con «php artisan migrate» general: en 8086 existe otra migración ajena pendiente
| (2026_11_20_190000_add_escuela_facultad_to_instituciones_afiliadas). Procedimiento completo en
| docs/anfeca/modulo-coordinacion/PERSISTENCIA_REVISION_DOCUMENTAL_FASE_6_3_1.md §9.
|
| Historial de SOLO INSERCIÓN de revisiones documentales hechas por la coordinación.
| - Cada fila es un evento inmutable. La revisión «vigente» de un documento es la última de su
|   cadena: la fila sin sucesor.
| - «Por revisar» no se guarda: es la ausencia de una revisión de la versión vigente.
| - «No aplica» no se guarda: lo calculan las reglas del trámite.
| - Para revisiones futuras, el PDM permanece apagado y no hay espejo hacia él.
|
| La definición de PostgreSQL es la normativa. La de SQLite es su equivalente para las
| pruebas automáticas en memoria.
|
| Límites homologados: observaciones de 10 a 1000 caracteres en resultados desfavorables
| (opcionales en «validado»); motivo_cambio de 10 a 1000 e independiente de las observaciones.
|
| ALCANCE DE LA PROTECCIÓN: los disparadores y restricciones impiden modificar o borrar el historial
| mediante operaciones ordinarias, incluso al rol propietario de la aplicación. Un propietario del
| esquema o un superusuario puede, de forma deliberada, desactivar disparadores
| (ALTER TABLE ... DISABLE TRIGGER) o eliminar restricciones. Eso no es una operación administrativa
| ordinaria; se mitiga con respaldos, auditoría de DDL y, en el futuro, con roles separados.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Claves de documento admitidas (= ExpedienteController::DOCUMENTOS). */
    public const DOCUMENTOS = [
        'titulo_origen_archivo', 'cedula_origen_archivo',
        'titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo',
        'constancia_antiguedad_archivo', 'comprobante_pago_archivo',
        'solicitud_articulo_14_archivo', 'constancia_articulo_14_archivo',
    ];

    public function up(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => $this->upPostgres(),
            'sqlite' => $this->upSqlite(),
            default => throw new RuntimeException('revisiones_documentales: motor no soportado.'),
        };
    }

    public function down(): void
    {
        // Nunca destruye historial: solo revierte si la tabla está vacía.
        if (DB::table('revisiones_documentales')->exists()) {
            throw new RuntimeException('revisiones_documentales contiene historial; la reversión se niega. Use el procedimiento de respaldo y recuperación.');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP VIEW IF EXISTS public.revisiones_documentales_vigentes;
                DROP TABLE IF EXISTS public.revisiones_documentales;
                DROP FUNCTION IF EXISTS public.rd_validar_insercion();
                DROP FUNCTION IF EXISTS public.rd_impedir_modificacion();
                SQL);

            return;
        }

        DB::unprepared(<<<'SQL'
            DROP VIEW IF EXISTS revisiones_documentales_vigentes;
            DROP TABLE IF EXISTS revisiones_documentales;
            SQL);
    }

    private function listaDocumentos(): string
    {
        return implode(', ', array_map(fn ($d) => "'{$d}'", self::DOCUMENTOS));
    }

    private function upPostgres(): void
    {
        $documentos = $this->listaDocumentos();

        DB::unprepared(<<<SQL
            CREATE TABLE public.revisiones_documentales (
                id                    bigint GENERATED ALWAYS AS IDENTITY,
                solicitud_id          bigint       NOT NULL,
                documento_clave       varchar(60)  NOT NULL,
                -- Versión revisada: estado del archivo al momento de revisar.
                archivo_estado        varchar(20)  NOT NULL,
                version_ruta          varchar(255),
                version_sha256        char(64),
                version_bytes         bigint,
                -- Resultado (solo resultados explícitos; «Por revisar» y «No aplica» no se guardan).
                resultado             varchar(20)  NOT NULL,
                observaciones         text,
                motivo_cambio         text,
                revision_anterior_id  bigint,
                -- Responsable (copia del nombre y rol al momento de la revisión).
                coordinador_id        bigint       NOT NULL,
                coordinador_nombre    varchar(255) NOT NULL,
                coordinador_rol       varchar(30)  NOT NULL,
                solicitud_estado      varchar(20)  NOT NULL,
                clave_idempotencia    uuid         NOT NULL,
                revisado_en           timestamptz  NOT NULL DEFAULT clock_timestamp(),

                CONSTRAINT revisiones_documentales_pkey PRIMARY KEY (id),
                CONSTRAINT rd_solicitud_fk FOREIGN KEY (solicitud_id)
                    REFERENCES public.solicitudes_certificacion (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT rd_coordinador_fk FOREIGN KEY (coordinador_id)
                    REFERENCES public.users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT rd_anterior_fk FOREIGN KEY (revision_anterior_id)
                    REFERENCES public.revisiones_documentales (id) ON UPDATE RESTRICT ON DELETE RESTRICT,

                CONSTRAINT rd_documento_ck CHECK (documento_clave IN ({$documentos})),
                CONSTRAINT rd_archivo_estado_ck CHECK (archivo_estado IN ('cargado', 'no_cargado', 'no_localizado')),
                CONSTRAINT rd_resultado_ck CHECK (resultado IN ('validado', 'con_observaciones', 'no_valido')),
                CONSTRAINT rd_version_ck CHECK (
                       (archivo_estado = 'cargado'       AND version_ruta IS NOT NULL AND version_sha256 IS NOT NULL AND version_bytes IS NOT NULL)
                    OR (archivo_estado = 'no_localizado' AND version_ruta IS NOT NULL AND version_sha256 IS NULL     AND version_bytes IS NULL)
                    OR (archivo_estado = 'no_cargado'    AND version_ruta IS NULL     AND version_sha256 IS NULL     AND version_bytes IS NULL)),
                CONSTRAINT rd_sha256_ck CHECK (version_sha256 IS NULL OR version_sha256 ~ '^[0-9a-f]{64}$'),
                CONSTRAINT rd_ruta_ck CHECK (version_ruta IS NULL OR (version_ruta LIKE 'anfeca/%' AND strpos(version_ruta, '..') = 0)),
                CONSTRAINT rd_bytes_ck CHECK (version_bytes IS NULL OR version_bytes >= 0),
                CONSTRAINT rd_validado_requiere_archivo_ck CHECK (resultado <> 'validado' OR archivo_estado = 'cargado'),
                CONSTRAINT rd_observaciones_ck CHECK (resultado = 'validado' OR char_length(btrim(coalesce(observaciones, ''))) >= 10),
                CONSTRAINT rd_observaciones_largo_ck CHECK (observaciones IS NULL OR char_length(observaciones) <= 1000),
                CONSTRAINT rd_motivo_ck CHECK (motivo_cambio IS NULL OR char_length(btrim(motivo_cambio)) BETWEEN 10 AND 1000),
                CONSTRAINT rd_motivo_solo_con_anterior_ck CHECK (revision_anterior_id IS NOT NULL OR motivo_cambio IS NULL),
                CONSTRAINT rd_rol_ck CHECK (coordinador_rol IN ('coordinador_nacional', 'coordinador_zona')),
                CONSTRAINT rd_nombre_ck CHECK (char_length(btrim(coordinador_nombre)) > 0),
                CONSTRAINT rd_solicitud_estado_ck CHECK (solicitud_estado = 'enviado'),

                -- Concurrencia e historial lineal: cada revisión tiene a lo sumo un sucesor...
                CONSTRAINT rd_sucesor_unico_uq UNIQUE (revision_anterior_id),
                -- ...y los reintentos de la misma petición no duplican filas.
                CONSTRAINT rd_idempotencia_uq UNIQUE (clave_idempotencia)
            );

            COMMENT ON TABLE public.revisiones_documentales IS
                'Fase 6.3.1. Historial de solo inserción de revisiones documentales de la coordinación. Vigente = fila sin sucesor.';

            -- Una sola primera revisión por documento: junto con rd_sucesor_unico_uq, la cadena no se bifurca.
            CREATE UNIQUE INDEX rd_primera_revision_uq
                ON public.revisiones_documentales (solicitud_id, documento_clave)
                WHERE revision_anterior_id IS NULL;
            CREATE INDEX rd_documento_idx ON public.revisiones_documentales (solicitud_id, documento_clave, id DESC);
            CREATE INDEX rd_coordinador_idx ON public.revisiones_documentales (coordinador_id, revisado_en DESC);

            -- Validaciones entre filas y con otras tablas; la fecha la fija el servidor de base de datos.
            CREATE OR REPLACE FUNCTION public.rd_validar_insercion() RETURNS trigger
                LANGUAGE plpgsql AS \$fn\$
            DECLARE
                v_estado varchar;
                v_rol    varchar;
                v_ant    public.revisiones_documentales%ROWTYPE;
            BEGIN
                NEW.revisado_en := clock_timestamp();

                SELECT estado INTO v_estado FROM public.solicitudes_certificacion WHERE id = NEW.solicitud_id;
                IF v_estado IS DISTINCT FROM 'enviado' THEN
                    RAISE EXCEPTION 'revisiones_documentales: solo se revisan solicitudes enviadas (estado actual: %)', v_estado
                        USING ERRCODE = 'check_violation';
                END IF;

                SELECT rol INTO v_rol FROM public.users WHERE id = NEW.coordinador_id;
                IF v_rol IS DISTINCT FROM NEW.coordinador_rol THEN
                    RAISE EXCEPTION 'revisiones_documentales: el rol registrado no corresponde al usuario'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.revision_anterior_id IS NOT NULL THEN
                    SELECT * INTO v_ant FROM public.revisiones_documentales WHERE id = NEW.revision_anterior_id;
                    IF v_ant.solicitud_id <> NEW.solicitud_id OR v_ant.documento_clave <> NEW.documento_clave THEN
                        RAISE EXCEPTION 'revisiones_documentales: la revisión anterior pertenece a otro documento'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF v_ant.resultado <> NEW.resultado AND NEW.motivo_cambio IS NULL THEN
                        RAISE EXCEPTION 'revisiones_documentales: cambiar un resultado previo exige motivo_cambio'
                            USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                RETURN NEW;
            END
            \$fn\$;

            CREATE OR REPLACE FUNCTION public.rd_impedir_modificacion() RETURNS trigger
                LANGUAGE plpgsql AS \$fn\$
            BEGIN
                RAISE EXCEPTION 'revisiones_documentales es un historial de solo inserción (% no permitido)', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END
            \$fn\$;

            CREATE TRIGGER rd_validar_insercion_trg BEFORE INSERT ON public.revisiones_documentales
                FOR EACH ROW EXECUTE FUNCTION public.rd_validar_insercion();
            CREATE TRIGGER rd_sin_update_delete_trg BEFORE UPDATE OR DELETE ON public.revisiones_documentales
                FOR EACH ROW EXECUTE FUNCTION public.rd_impedir_modificacion();
            CREATE TRIGGER rd_sin_truncate_trg BEFORE TRUNCATE ON public.revisiones_documentales
                FOR EACH STATEMENT EXECUTE FUNCTION public.rd_impedir_modificacion();

            -- Segunda barrera para otros roles. NO se revoca UPDATE al rol propietario: las verificaciones de
            -- llave foránea (incluida la autorreferencia rd_anterior_fk) usan SELECT ... FOR KEY SHARE, que
            -- requiere ese privilegio. El endurecimiento por roles separados (propietario de esquema distinto
            -- del rol de la aplicación, que solo recibe SELECT e INSERT) se describe en el documento de diseño §6.3.
            REVOKE ALL ON public.revisiones_documentales FROM PUBLIC;

            CREATE VIEW public.revisiones_documentales_vigentes AS
                SELECT r.*
                  FROM public.revisiones_documentales r
                 WHERE NOT EXISTS (SELECT 1 FROM public.revisiones_documentales s WHERE s.revision_anterior_id = r.id);
            SQL);
    }

    private function upSqlite(): void
    {
        $documentos = $this->listaDocumentos();

        DB::unprepared(<<<SQL
            CREATE TABLE revisiones_documentales (
                id                    integer PRIMARY KEY AUTOINCREMENT,
                solicitud_id          integer NOT NULL REFERENCES solicitudes_certificacion (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                documento_clave       varchar(60) NOT NULL CHECK (documento_clave IN ({$documentos})),
                archivo_estado        varchar(20) NOT NULL CHECK (archivo_estado IN ('cargado', 'no_cargado', 'no_localizado')),
                version_ruta          varchar(255) CHECK (version_ruta IS NULL OR (version_ruta LIKE 'anfeca/%' AND instr(version_ruta, '..') = 0)),
                version_sha256        char(64) CHECK (version_sha256 IS NULL OR (length(version_sha256) = 64 AND version_sha256 NOT GLOB '*[^0-9a-f]*')),
                version_bytes         integer CHECK (version_bytes IS NULL OR version_bytes >= 0),
                resultado             varchar(20) NOT NULL CHECK (resultado IN ('validado', 'con_observaciones', 'no_valido')),
                observaciones         text CHECK (observaciones IS NULL OR length(observaciones) <= 1000),
                motivo_cambio         text CHECK (motivo_cambio IS NULL OR length(trim(motivo_cambio)) BETWEEN 10 AND 1000),
                revision_anterior_id  integer REFERENCES revisiones_documentales (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                coordinador_id        integer NOT NULL REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                coordinador_nombre    varchar(255) NOT NULL CHECK (length(trim(coordinador_nombre)) > 0),
                coordinador_rol       varchar(30) NOT NULL CHECK (coordinador_rol IN ('coordinador_nacional', 'coordinador_zona')),
                solicitud_estado      varchar(20) NOT NULL CHECK (solicitud_estado = 'enviado'),
                clave_idempotencia    varchar(36) NOT NULL UNIQUE,
                revisado_en           datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (revision_anterior_id),
                CHECK (   (archivo_estado = 'cargado'       AND version_ruta IS NOT NULL AND version_sha256 IS NOT NULL AND version_bytes IS NOT NULL)
                       OR (archivo_estado = 'no_localizado' AND version_ruta IS NOT NULL AND version_sha256 IS NULL     AND version_bytes IS NULL)
                       OR (archivo_estado = 'no_cargado'    AND version_ruta IS NULL     AND version_sha256 IS NULL     AND version_bytes IS NULL)),
                CHECK (resultado <> 'validado' OR archivo_estado = 'cargado'),
                CHECK (resultado = 'validado' OR length(trim(coalesce(observaciones, ''))) >= 10),
                CHECK (revision_anterior_id IS NOT NULL OR motivo_cambio IS NULL)
            );
            CREATE UNIQUE INDEX rd_primera_revision_uq ON revisiones_documentales (solicitud_id, documento_clave) WHERE revision_anterior_id IS NULL;
            CREATE INDEX rd_documento_idx ON revisiones_documentales (solicitud_id, documento_clave, id DESC);
            CREATE INDEX rd_coordinador_idx ON revisiones_documentales (coordinador_id, revisado_en DESC);

            CREATE TRIGGER rd_solicitud_enviada_trg BEFORE INSERT ON revisiones_documentales
            WHEN (SELECT estado FROM solicitudes_certificacion WHERE id = NEW.solicitud_id) IS NOT 'enviado'
            BEGIN SELECT RAISE(ABORT, 'revisiones_documentales: solo se revisan solicitudes enviadas'); END;

            CREATE TRIGGER rd_rol_trg BEFORE INSERT ON revisiones_documentales
            WHEN (SELECT rol FROM users WHERE id = NEW.coordinador_id) IS NOT NEW.coordinador_rol
            BEGIN SELECT RAISE(ABORT, 'revisiones_documentales: el rol registrado no corresponde al usuario'); END;

            CREATE TRIGGER rd_anterior_mismo_documento_trg BEFORE INSERT ON revisiones_documentales
            WHEN NEW.revision_anterior_id IS NOT NULL AND NOT EXISTS (
                SELECT 1 FROM revisiones_documentales a WHERE a.id = NEW.revision_anterior_id
                   AND a.solicitud_id = NEW.solicitud_id AND a.documento_clave = NEW.documento_clave)
            BEGIN SELECT RAISE(ABORT, 'revisiones_documentales: la revisión anterior pertenece a otro documento'); END;

            CREATE TRIGGER rd_motivo_cambio_trg BEFORE INSERT ON revisiones_documentales
            WHEN NEW.motivo_cambio IS NULL AND EXISTS (
                SELECT 1 FROM revisiones_documentales a WHERE a.id = NEW.revision_anterior_id AND a.resultado <> NEW.resultado)
            BEGIN SELECT RAISE(ABORT, 'revisiones_documentales: cambiar un resultado previo exige motivo_cambio'); END;

            CREATE TRIGGER rd_sin_update_trg BEFORE UPDATE ON revisiones_documentales
            BEGIN SELECT RAISE(ABORT, 'revisiones_documentales es un historial de solo inserción (UPDATE no permitido)'); END;

            CREATE TRIGGER rd_sin_delete_trg BEFORE DELETE ON revisiones_documentales
            BEGIN SELECT RAISE(ABORT, 'revisiones_documentales es un historial de solo inserción (DELETE no permitido)'); END;

            CREATE VIEW revisiones_documentales_vigentes AS
                SELECT r.* FROM revisiones_documentales r
                 WHERE NOT EXISTS (SELECT 1 FROM revisiones_documentales s WHERE s.revision_anterior_id = r.id);
            SQL);
    }
};
