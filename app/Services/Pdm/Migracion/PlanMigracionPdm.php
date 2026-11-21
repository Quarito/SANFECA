<?php

namespace App\Services\Pdm\Migracion;

use App\Services\Pdm\NormalizadorCatalogos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Calcula, SOLO LEYENDO public y los catálogos del PDM, las filas que debe tener
 * anfeca_modelo y las incidencias que lo impiden. No escribe nada.
 *
 * Mapeo: docs/anfeca/PLAN_INTEGRACION_FUNCIONAL_PDM.md §3 · Reglas: DECISIONES_DATOS_F1.md.
 *
 * Estructura de $filas[tabla]: lista de filas (columna => valor). Las tablas con ID
 * conservado lo traen explícito; las demás se identifican por su clave natural
 * (ver ConciliadorPdm::CLAVES).
 */
class PlanMigracionPdm
{
    public const ZONA_HORARIA_PDM = 'America/Mexico_City';

    /** @var array<string, list<array<string,mixed>>> */
    public array $filas = [];

    /** @var list<Incidencia> */
    public array $incidencias = [];

    private array $cat = [];

    private array $decisiones = [];

    private NormalizadorCatalogos $norm;

    /** IDs incluidos en el plan, por tabla PDM. */
    private array $incluidos = [];

    /** Alcance acotado (F3): ['usuarios' => [ids]], ['solicitudes' => [ids]] o null para todo. */
    private ?array $alcance = null;

    public static function construir(?array $decisiones = null, ?array $alcance = null): self
    {
        $plan = new self();
        $plan->decisiones = $plan->decisionesDeEstaBase($decisiones ?? (array) config('anfeca_pdm_decisiones'));
        $plan->alcance = $alcance;
        $plan->norm = new NormalizadorCatalogos();
        $plan->cargarCatalogos();

        if ($alcance === null) {
            $plan->roles();
            $plan->zonas();
            $plan->instituciones();
            $plan->personasYUsuarios();
            $plan->coordinadores();
            $plan->historicos();
            $plan->solicitudes();
        } else {
            // F3: mismas reglas, solo para las personas y solicitudes afectadas por una operación.
            // Instituciones y usuarios ya existentes en el PDM se toman de anfeca_modelo.
            $plan->incluidos['institucion'] = DB::table('anfeca_modelo.institucion')->pluck('inst_id_institucion')->flip()->map(fn () => true)->all();
            $plan->personasYUsuarios($alcance['usuarios'] ?? []);
            if (isset($alcance['solicitudes'])) {
                $plan->solicitudes($alcance['solicitudes']);
            }
        }

        return $plan;
    }

    /** @return list<Incidencia> */
    public function incidenciasDe(bool $bloqueantes): array
    {
        return array_values(array_filter($this->incidencias, fn (Incidencia $i) => $i->bloqueante === $bloqueantes));
    }

    // ------------------------------------------------------------------
    // Resultado
    // ------------------------------------------------------------------

    public function bloqueantes(): array
    {
        return array_values(array_filter($this->incidencias, fn (Incidencia $i) => $i->bloqueante));
    }

    public function conteos(): array
    {
        return array_map('count', $this->filas);
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    private function agregar(string $tabla, array $fila): void
    {
        $this->filas[$tabla][] = $fila;
    }

    /** Secciones de decisiones que se refieren a IDs concretos de public. */
    private const DECISIONES_POR_REGISTRO = ['instituciones', 'domicilios', 'certificados', 'solicitudes'];

    /**
     * Las decisiones por registro usan IDs de public, que solo identifican al mismo
     * registro dentro de la MISMA base. Si el archivo no declara su base, o declara otra,
     * se descartan todas (sin aplicar ninguna) y se registra una incidencia bloqueante:
     * así los IDs de la base de ensayo nunca se aplican a registros distintos de otro servidor.
     */
    private function decisionesDeEstaBase(array $decisiones): array
    {
        $porRegistro = array_filter(self::DECISIONES_POR_REGISTRO, fn ($k) => !empty($decisiones[$k] ?? []));
        if ($porRegistro === []) {
            return $decisiones;
        }

        $declarada = $decisiones['base_de_datos'] ?? null;
        $actual = DB::connection()->getDatabaseName();
        if (is_string($declarada) && $declarada !== '' && $declarada === $actual) {
            return $decisiones;
        }

        $this->incidencia('DECISIONES_DE_OTRA_BASE', true, 'config', 'anfeca_pdm_decisiones',
            ($declarada ? "Las decisiones por registro pertenecen a la base '{$declarada}'"
                : 'Las decisiones por registro no declaran su base (base_de_datos)')
            .'; no se aplicó ninguna ('.implode(', ', $porRegistro).'). Regístrelas para esta base.', 'D-0');

        foreach (self::DECISIONES_POR_REGISTRO as $k) {
            $decisiones[$k] = [];
        }

        return $decisiones;
    }

    private function incidencia(string $codigo, bool $bloqueante, string $tabla, int|string|null $id, string $mensaje, ?string $decision = null, ?string $campo = null): void
    {
        $this->incidencias[] = new Incidencia($codigo, $bloqueante, $tabla, $id, $mensaje, $decision, $campo);
    }

    private function largoExcedido(string $tabla, int|string $id, string $columna, ?string $valor, int $limite): bool
    {
        if ($valor !== null && mb_strlen($valor) > $limite) {
            $this->incidencia('LONGITUD', true, $tabla, $id, "{$columna}: ".mb_strlen($valor)." caracteres; el PDM admite {$limite}. No se trunca.", 'D-4', $columna);

            return true;
        }

        return false;
    }

    /** Fecha y hora locales (America/Mexico_City) a partir de un timestamp de Laravel. */
    private function local(?string $marca): ?CarbonImmutable
    {
        return $marca === null ? null
            : CarbonImmutable::parse($marca, config('app.timezone'))->setTimezone(self::ZONA_HORARIA_PDM);
    }

    private function cargarCatalogos(): void
    {
        $mapa = fn (string $t, string $k, string $v) => DB::table("anfeca_modelo.{$t}")->pluck($v, $k)->all();

        $this->cat = [
            'entidades' => $mapa('entidad_federativa', 'enfe_nombre', 'enfe_id_entidad_federativa'),
            'tipos_comunicacion' => $mapa('tipo_comunicacion', 'tico_tipo_comunicacion', 'tico_id_tipo_comunicacion'),
            'estados_solicitud' => $mapa('c_estado_solicitud', 'ceso_estado_solicitud', 'ceso_id_c_estado_solicitud'),
            'documentos' => $mapa('documento', 'docu_descripcion', 'docu_id_documento'),
            'estados_documento' => $mapa('estado_documento', 'esdo_estado_documento', 'esdo_id_estado_documento'),
            'articulos' => $mapa('articulo_subindice', 'arce_subindice', 'arce_id_articulo'),
            'areas' => $mapa('area_certificacion', 'area_descripcion', 'area_id_area_certificacion'),
            'tipos_personal' => $mapa('tipo_personal_academico', 'tipa_descripcion', 'tipa_id_tipo_personal_academico'),
            'niveles' => $mapa('nivel_profesional', 'nipr_nivel_profesional', 'nipr_id_nivel_profesional'),
        ];

        $this->cat['estudios'] = [];
        foreach (DB::table('anfeca_modelo.estudio_profesional')->where('espr_estado', 'ACT')->get() as $e) {
            $this->cat['estudios'][$e->espr_id_nivel_profesional][$e->espr_nombre] = true;
        }
    }

    private function idCatalogoConfig(string $grupo, string $clave): int
    {
        return (int) $this->cat[$grupo][config("anfeca_pdm.catalogos.{$grupo}.{$clave}")];
    }

    // ------------------------------------------------------------------
    // Roles, privilegios, zonas, instituciones
    // ------------------------------------------------------------------

    private function roles(): void
    {
        foreach (DB::table('public.roles')->orderBy('id')->get() as $r) {
            $this->agregar('rol', ['rol_id_rol' => $r->id, 'rol_nombre' => $r->nombre]);
            $this->incluidos['rol_nombre'][$r->nombre] = $r->id;
        }

        // Valores de users.rol que no están en public.roles: se registra el valor en uso, tal cual.
        foreach (DB::table('public.users')->distinct()->pluck('rol') as $nombre) {
            if (!isset($this->incluidos['rol_nombre'][$nombre])) {
                $this->agregar('rol', ['rol_id_rol' => null, 'rol_nombre' => $nombre]);
                $this->incluidos['rol_nombre'][$nombre] = null;
                $this->incidencia('ROL_DESDE_USERS', false, 'users', null, "El rol '{$nombre}' se usa en users.rol y no existe en public.roles: se crea en ROL con ese nombre.");
            }
        }

        foreach (DB::table('public.privilegios')->orderBy('id')->get() as $p) {
            if ($this->largoExcedido('privilegios', $p->id, 'descripcion', $p->descripcion, 150)) {
                continue;
            }
            $this->agregar('privilegio', [
                'priv_id_privilegio' => $p->id, 'priv_nombre' => $p->nombre, 'priv_descripcion' => $p->descripcion ?? '',
            ]);
            $this->incluidos['privilegio'][$p->id] = true;
            if ($p->descripcion === null) {
                $this->incidencia('PRIVILEGIO_SIN_DESCRIPCION', false, 'privilegios', $p->id, 'Sin descripción: se registra vacía (PRIV_DESCRIPCION es obligatoria).');
            }
        }

        foreach (DB::table('public.rol_privilegio')->orderBy('id')->get() as $rp) {
            if (isset($this->incluidos['privilegio'][$rp->privilegio_id])) {
                $this->agregar('rol_privilegio', [
                    'ropr_id_rol_privilegio' => $rp->id, 'ropr_id_rol' => $rp->rol_id, 'ropr_id_privilegio' => $rp->privilegio_id,
                ]);
            }
        }
    }

    private function zonas(): void
    {
        foreach (DB::table('public.zonas')->orderBy('id')->get() as $z) {
            $this->agregar('zona', ['zona_id_zona' => $z->id, 'zona_siglas' => $z->siglas, 'zona_descripcion' => $z->descripcion]);
            $this->incluidos['zona'][$z->id] = $z->siglas;
        }
    }

    private function instituciones(): void
    {
        $decisiones = (array) ($this->decisiones['instituciones'] ?? []);

        foreach (DB::table('public.instituciones_afiliadas')->orderBy('id')->get() as $i) {
            $d = $decisiones[$i->id] ?? null;

            if ($d === 'no_migrar') {
                $this->incidencia('INSTITUCION_NO_MIGRADA', false, 'instituciones_afiliadas', $i->id, 'Decisión registrada: no migrar. Las personas quedan sin PERS_ID_INSTITUCION.', 'D-11');
                continue;
            }

            // INST_FACULTAD es obligatoria en el PDM; escuela_facultad es opcional en Laravel.
            // Fuente: escuela_facultad capturada en public; si está vacía, la facultad confirmada en D-11.
            // Si faltan ambas, la institución queda bloqueada (no se inventa un nombre).
            $facultadPublic = trim((string) ($i->escuela_facultad ?? ''));
            $facultadDecision = is_array($d) ? trim((string) ($d['facultad'] ?? '')) : '';

            if (!is_array($d) || blank($d['numero_afiliacion'] ?? null) || ($facultadPublic === '' && $facultadDecision === '')) {
                $this->incidencia('INSTITUCION_SIN_DATOS_OFICIALES', true, 'instituciones_afiliadas', $i->id,
                    'Faltan número de afiliación (≤7) y facultad confirmados con la fuente oficial (escuela_facultad o D-11).', 'D-11');
                continue;
            }

            if ($facultadPublic !== '' && $facultadDecision !== '' && $facultadPublic !== $facultadDecision) {
                $this->incidencia('INSTITUCION_FACULTAD_DISCREPANTE', true, 'instituciones_afiliadas', $i->id,
                    'La facultad de D-11 no coincide con escuela_facultad registrada; confirme cuál es la oficial.', 'D-11', 'escuela_facultad');
                continue;
            }

            $facultad = $facultadPublic !== '' ? $facultadPublic : $facultadDecision;

            $numero = trim((string) $d['numero_afiliacion']);
            $zona = $i->zona_id !== null ? ($this->incluidos['zona'][$i->zona_id] ?? null) : null;
            $zona ??= $i->zona_anfeca;

            $errores = [];
            if (mb_strlen($numero) > 7) {
                $errores[] = 'el número de afiliación registrado en la decisión excede 7 caracteres';
            }
            if (blank($i->nombre) || mb_strlen($i->nombre) > 300) {
                $errores[] = 'nombre vacío o mayor de 300 caracteres';
            }
            if (blank($zona) || mb_strlen($zona) > 80) {
                $errores[] = 'zona vacía o mayor de 80 caracteres';
            }
            if (mb_strlen($facultad) > 500) {
                $errores[] = 'facultad mayor de 500 caracteres';
            }
            if ($errores !== []) {
                $this->incidencia('INSTITUCION_INVALIDA', true, 'instituciones_afiliadas', $i->id, implode('; ', $errores).'.', 'D-11');
                continue;
            }

            $this->agregar('institucion', [
                'inst_id_institucion' => $i->id, 'inst_numero_afiliacion' => $numero, 'inst_nombre' => $i->nombre,
                'inst_facultad' => $facultad, 'inst_zona' => $zona,
                'ins_coord_regional_cert_academi' => null,
                'inst_id_zona' => $i->zona_id, 'inst_campus' => $i->campus,
            ]);
            $this->incluidos['institucion'][$i->id] = true;

            if (filled($i->sede)) {
                if (!$this->largoExcedido('instituciones_afiliadas', $i->id, 'sede', $i->sede, 150)) {
                    $this->agregar('sede', ['inst_id_institucion' => $i->id, 'sede_descripcion' => $i->sede]);
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Personas, usuarios, domicilio, contacto
    // ------------------------------------------------------------------

    private function personasYUsuarios(?array $soloUsuarios = null): void
    {
        $decisionesDom = (array) ($this->decisiones['domicilios'] ?? []);

        // Institución de la formación más reciente de cada usuario.
        $instituciones = DB::table('public.formaciones_academicas_solicitud as f')
            ->join('public.solicitudes_certificacion as s', 's.id', '=', 'f.solicitud_id')
            ->whereNotNull('f.institucion_afiliada_id')
            ->orderBy('s.id')->get(['s.user_id', 'f.institucion_afiliada_id'])
            ->pluck('institucion_afiliada_id', 'user_id')->all();

        $consultaUsuarios = DB::table('public.users');
        $consultaDatos = DB::table('public.datos_solicitantes')->orderBy('id');
        if ($soloUsuarios !== null) {
            $consultaUsuarios->whereIn('id', $soloUsuarios);
            $consultaDatos->whereIn('user_id', $soloUsuarios);
        }
        $usuarios = $consultaUsuarios->get()->keyBy('id');

        foreach ($consultaDatos->get() as $d) {
            $u = $usuarios[$d->user_id] ?? null;
            $errores = false;

            foreach ([['nombres', 150], ['apellido_paterno', 60], ['apellido_materno', 60], ['rfc', 13], ['cedula_profesional', 30]] as [$col, $lim]) {
                $errores = $this->largoExcedido('datos_solicitantes', $d->id, $col, $d->{$col}, $lim) || $errores;
            }

            if (blank($d->curp)) {
                $this->incidencia('PERSONA_SIN_CURP', true, 'datos_solicitantes', $d->id, 'PERS_CURP es obligatoria.', 'D-5');
                $errores = true;
            }

            $genero = $this->norm->genero($d->genero);
            if ($genero->revision) {
                $this->incidencia('GENERO_EN_REVISION', true, 'datos_solicitantes', $d->id, (string) $genero->motivo);
                $errores = true;
            }

            if ($errores) {
                continue;
            }

            $institucion = $instituciones[$d->user_id] ?? null;
            if ($institucion !== null && !isset($this->incluidos['institucion'][$institucion])) {
                $this->incidencia('PERSONA_SIN_INSTITUCION', false, 'datos_solicitantes', $d->id,
                    "Su institución ({$institucion}) no se migra todavía (D-11): PERS_ID_INSTITUCION queda vacío.", 'D-11');
                $institucion = null;
            }

            $this->agregar('persona', [
                'pers_id_persona' => $d->id, 'pers_id_institucion' => $institucion,
                'pers_nombre' => $d->nombres, 'pers_apellido_paterno' => $d->apellido_paterno,
                'pers_apellido_materno' => $d->apellido_materno, 'pers_curp' => $d->curp, 'pers_rfc' => $d->rfc,
                'pers_genero' => $genero->valor, 'pers_num_afiliacion_institucion' => null, 'pers_activo' => true,
                'pers_cedula_profesional' => $d->cedula_profesional,
                'pers_datos_autorizados' => (bool) $d->datos_autorizados, 'pers_fecha_autorizacion' => $d->fecha_autorizacion,
                'pers_creado_en' => $d->created_at, 'pers_actualizado_en' => $d->updated_at,
            ]);
            $this->incluidos['persona_por_usuario'][$d->user_id] = $d->id;

            // USUARIO (D-1 A: mismo ID que users; users sigue autenticando).
            if ($u !== null && !$this->largoExcedido('users', $u->id, 'correo_electronico', $u->email, 150)) {
                $this->agregar('usuario', [
                    'usua_id_usuario' => $u->id, 'usua_id_persona' => $d->id,
                    'usua_id_rol' => $this->incluidos['rol_nombre'][$u->rol] ?? null, '_rol_nombre' => $u->rol,
                    'usua_nombre' => $u->email, 'usua_contrasenia' => $u->password, 'usua_estatus' => 'ACTIVO',
                ]);
                $this->incluidos['usuario'][$u->id] = true;

                // F4: selección de certificación vigente del usuario (copia fiel).
                $seleccion = DB::table('public.selecciones_certificacion')->where('user_id', $u->id)->first();
                if ($seleccion !== null) {
                    $this->agregar('seleccion_certificacion', [
                        'secc_id_seleccion' => $seleccion->id, 'secc_id_usuario' => $u->id,
                        'secc_certificacion' => $seleccion->certificacion, 'secc_tipo_personal' => $seleccion->tipo_personal,
                        'secc_area_certificacion' => $seleccion->area_certificacion,
                        'secc_creado_en' => $seleccion->created_at, 'secc_actualizado_en' => $seleccion->updated_at,
                    ]);
                }
            }

            $this->domicilio($d, $decisionesDom[$d->id] ?? null);
            $this->comunicaciones($d);
        }

        if ($soloUsuarios !== null) {
            return;
        }

        // Usuarios sin datos personales: se conservan en public.users (D-1 A).
        $todosConDatos = DB::table('public.datos_solicitantes')->pluck('user_id')->flip()->all();
        foreach ($usuarios as $u) {
            if (!isset($todosConDatos[$u->id])) {
                $this->incidencia('USUARIO_SIN_DATOS_PERSONALES', false, 'users', $u->id,
                    'Sin datos personales: se conserva en public.users (puede iniciar sesión); se creará en el PDM al completar su registro.');
            }
        }
    }

    private function domicilio(object $d, mixed $decision): void
    {
        $r = $this->norm->entidadFederativa($d->entidad_federativa);
        $entidad = $r->revision ? null : $r->valor;

        if ($entidad === null) {
            if ($decision === 'sin_domicilio') {
                $this->incidencia('DOMICILIO_NO_MIGRADO', false, 'datos_solicitantes', $d->id, 'Decisión registrada: sin domicilio en el PDM; public lo conserva.', 'D-13');

                return;
            }
            if (is_array($decision) && isset($this->cat['entidades'][$decision['entidad'] ?? ''])) {
                $entidad = $decision['entidad'];
                $this->incidencia('ENTIDAD_POR_DECISION', false, 'datos_solicitantes', $d->id, "Entidad asignada por decisión registrada: {$entidad}.", 'D-13');
            } else {
                $this->incidencia('ENTIDAD_EN_REVISION', true, 'datos_solicitantes', $d->id,
                    "Entidad '{$d->entidad_federativa}' sin equivalencia verificable ({$r->motivo})", 'D-13', 'entidad_federativa');

                return;
            }
        }

        $errores = false;
        foreach ([['calle', 170], ['numero_exterior', 6], ['numero_interior', 4]] as [$col, $lim]) {
            $errores = $this->largoExcedido('datos_solicitantes', $d->id, $col, $d->{$col}, $lim) || $errores;
        }
        if ($errores) {
            return;
        }

        $this->agregar('domicilio', [
            'domi_id_persona' => $d->id, 'domi_id_entidad_federativa' => (int) $this->cat['entidades'][$entidad],
            'domi_calle' => $d->calle, 'domi_numero_ext' => $d->numero_exterior, 'domi_numero_int' => $d->numero_interior,
            'domi_colonia' => $d->colonia, 'domi_municipio' => $d->municipio, 'domi_codigo_postal' => $d->codigo_postal,
        ]);
    }

    private function comunicaciones(object $d): void
    {
        $tipos = (array) config('anfeca_pdm.catalogos.tipos_comunicacion');

        foreach (['TEL_PARTICULAR' => 'telefono_particular', 'TEL_CELULAR' => 'telefono_celular', 'CORREO' => 'correo_electronico'] as $clave => $columna) {
            if (filled($d->{$columna})) {
                $this->agregar('comunicacion', [
                    'cumu_id_persona' => $d->id,
                    'comu_id_tipo_comunicacion' => (int) $this->cat['tipos_comunicacion'][$tipos[$clave]],
                    'comu_valor' => $d->{$columna}, 'comu_visible' => null,
                ]);
            }
        }
    }

    private function coordinadores(): void
    {
        foreach (DB::table('public.coordinador_zona')->orderBy('id')->get() as $c) {
            if (isset($this->incluidos['usuario'][$c->user_id], $this->incluidos['zona'][$c->zona_id])) {
                $this->agregar('coordinador_zona', ['cozo_id_zona' => $c->zona_id, 'cozo_id_usuario' => $c->user_id]);
            } else {
                $this->incidencia('COORDINADOR_SIN_USUARIO', false, 'coordinador_zona', $c->id, 'Su usuario o zona no se migra; se conserva en public.');
            }
        }
    }

    // ------------------------------------------------------------------
    // Certificados históricos (D-9 y D-14)
    // ------------------------------------------------------------------

    private function historicos(): void
    {
        $decisiones = (array) ($this->decisiones['certificados'] ?? []);
        $anios = ['PRIMERA_VEZ' => 'primera_vez'];
        for ($n = 1; $n <= 7; $n++) {
            $anios["REFRENDO_{$n}"] = "refrendo_{$n}";
        }

        foreach (DB::table('public.certificados_historicos')->orderBy('id')->get() as $h) {
            $d = $decisiones[$h->id] ?? null;
            $numero = $this->norm->numeroCertificado($h->numero_certificado);
            $motivos = [];

            if ($numero->revision && is_array($d) && isset($d['numero'])) {
                $corregido = $this->norm->numeroCertificado((string) $d['numero']);
                if (!$corregido->revision) {
                    $numero = $corregido;
                    $this->incidencia('NUMERO_POR_DECISION', false, 'certificados_historicos', $h->id, 'Número normalizado por decisión registrada.', 'D-14');
                }
            }
            if ($numero->revision) {
                $motivos[] = (string) $numero->motivo;
            }

            $eventos = [];
            foreach ($anios as $tipo => $columna) {
                $anio = $this->norm->anio($h->{$columna});
                if ($anio->revision) {
                    $motivos[] = "{$columna}: {$anio->motivo}";
                } elseif ($anio->valor !== null) {
                    $eventos[$tipo] = $anio->valor;
                }
            }

            $estado = $motivos === [] ? 'NORMALIZADO' : 'REVISION';

            // Copia fiel de la fila (siempre, también en revisión).
            $this->agregar('historico_certificado_importacion', [
                'hcim_id_importacion' => $h->id,
                'hcim_id_usuario' => isset($this->incluidos['usuario'][$h->user_id]) ? $h->user_id : null,
                'hcim_zona' => $h->zona, 'hcim_nombre_historico' => $h->nombre_historico,
                'hcim_correo_historico' => $h->correo_historico, 'hcim_apellido_paterno' => $h->apellido_paterno,
                'hcim_apellido_materno' => $h->apellido_materno, 'hcim_nombres' => $h->nombres,
                'hcim_curp_historica' => $h->curp_historica, 'hcim_institucion_historica' => $h->institucion_historica,
                'hcim_certificacion' => $h->certificacion, 'hcim_primera_vez' => $h->primera_vez,
                'hcim_refrendo_1' => $h->refrendo_1, 'hcim_refrendo_2' => $h->refrendo_2, 'hcim_refrendo_3' => $h->refrendo_3,
                'hcim_refrendo_4' => $h->refrendo_4, 'hcim_refrendo_5' => $h->refrendo_5, 'hcim_refrendo_6' => $h->refrendo_6,
                'hcim_refrendo_7' => $h->refrendo_7, 'hcim_columna_sin_encabezado' => $h->columna_sin_encabezado,
                'hcim_refrendo_correspondiente' => $h->refrendo_correspondiente, 'hcim_numero_certificado' => $h->numero_certificado,
                'hcim_lote_importacion' => $h->lote_importacion, 'hcim_fila_origen' => $h->fila_origen,
                'hcim_estado_vinculacion' => $h->estado_vinculacion,
                'hcim_estado_normalizacion' => $estado,
                'hcim_motivo_normalizacion' => $motivos === [] ? null : mb_substr(implode(' | ', $motivos), 0, 300),
                'hcim_creado_en' => $h->created_at, 'hcim_actualizado_en' => $h->updated_at,
            ]);

            if ($h->user_id !== null && !isset($this->incluidos['usuario'][$h->user_id])) {
                $this->incidencia('HISTORICO_SIN_USUARIO_PDM', false, 'certificados_historicos', $h->id, 'Vinculado a un usuario que no se migra; el vínculo se conserva en public.');
            }

            if ($estado === 'REVISION') {
                $this->incidencia(
                    'CERTIFICADO_EN_REVISION',
                    $d !== 'conservar_en_revision',
                    'certificados_historicos', $h->id,
                    'Se conserva íntegro en la importación, sin eventos normalizados: '.implode(' | ', $motivos),
                    'D-14'
                );
                continue;
            }

            $certificacion = $h->certificacion !== null && mb_strlen($h->certificacion) <= 20 ? $h->certificacion : null;
            $curp = $h->curp_historica !== null && mb_strlen($h->curp_historica) <= 18 ? $h->curp_historica : null;

            foreach ($eventos as $tipo => $anio) {
                $this->agregar('historico_certificado', [
                    'hice_id_importacion' => $h->id, 'hice_tipo' => $tipo, 'hice_anio_certificacion' => $anio,
                    'hice_numero_certificacion' => null, 'hice_folio_certificado' => $numero->valor,
                    'hice_curp' => $curp, 'hice_area_certificacion' => null, 'hice_certificacion' => $certificacion,
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Solicitudes, eventos, formación, documentos y pago
    // ------------------------------------------------------------------

    private function solicitudes(?array $soloSolicitudes = null): void
    {
        $selecciones = DB::table('public.selecciones_certificacion')->get()->keyBy('user_id');
        $refrendos = DB::table('public.datos_refrendo')->get()->keyBy('solicitud_id');
        $certificados = DB::table('public.certificados_historicos')->pluck('numero_certificado', 'id');
        $estados = (array) config('anfeca_pdm.catalogos.estados_solicitud');
        $decisionesSolicitudes = (array) ($this->decisiones['solicitudes'] ?? []);

        $consulta = DB::table('public.solicitudes_certificacion')->orderBy('id');
        if ($soloSolicitudes !== null) {
            $consulta->whereIn('id', $soloSolicitudes);
        }

        foreach ($consulta->get() as $s) {
            $persona = $this->incluidos['persona_por_usuario'][$s->user_id] ?? null;

            if ($persona === null) {
                $this->incidencia('SOLICITUD_SIN_PERSONA', true, 'solicitudes_certificacion', $s->id,
                    'Su usuario no tiene persona en el PDM (sin datos personales o con incidencias): SOLI_ID_PERSONA es obligatoria.');
                continue;
            }

            $errores = [];
            if (!in_array($s->tipo_tramite, ['inicial', 'refrendo'], true)) {
                $errores[] = "tipo de trámite '{$s->tipo_tramite}' no válido";
            }
            if (!in_array($s->certificacion, ['ANFECA', 'CACSLA', 'CACECA'], true)) {
                $errores[] = 'certificación no válida';
            }
            if (!in_array($s->estado, ['borrador', 'enviado'], true)) {
                $errores[] = "estado '{$s->estado}' no válido";
            }
            if ($s->estado === 'enviado' && $s->fecha_envio === null) {
                $errores[] = 'enviada sin fecha de envío';
            }
            if ($s->compromiso_aceptado && $s->compromiso_aceptado_en === null) {
                $errores[] = 'compromiso aceptado sin fecha';
            }

            // Área y tipo de personal; N-6: selección del mismo usuario y certificación.
            // Valores históricos no reconocidos solo se corrigen mediante decisión
            // explícita, por solicitud y con fuente verificable.
            $seleccion = $selecciones[$s->user_id] ?? null;
            $decisionSolicitud = (array) ($decisionesSolicitudes[$s->id] ?? []);
            $catalogo = [];
            foreach (['area_certificacion' => ['areas_certificacion', 'areas'], 'tipo_personal' => ['tipos_personal_academico', 'tipos_personal']] as $col => [$grupo, $mapa]) {
                $valor = $s->{$col};
                if (blank($valor) && $seleccion !== null && $seleccion->certificacion === $s->certificacion) {
                    $valor = $seleccion->{$col};
                    $this->incidencia('N6_DESDE_SELECCION', false, 'solicitudes_certificacion', $s->id, "{$col} vacío: se toma de selecciones_certificacion (regla N-6).");
                }
                $r = $this->norm->catalogoExacto($grupo, $valor);
                if ($r->revision && filled($decisionSolicitud['fuente'] ?? null)
                    && filled($decisionSolicitud[$col] ?? null)) {
                    $porDecision = $this->norm->catalogoExacto($grupo, $decisionSolicitud[$col]);
                    if (!$porDecision->revision) {
                        $r = $porDecision;
                        $this->incidencia('CATALOGO_POR_DECISION', false, 'solicitudes_certificacion', $s->id,
                            "{$col}: se usa el valor del catálogo confirmado en decisión registrada (sin alterar public).",
                            'D-15', $col);
                    }
                }
                if ($r->revision) {
                    $errores[] = "{$col}: {$r->motivo}";
                } else {
                    $catalogo[$col] = (int) $this->cat[$mapa][$r->valor];
                }
            }

            $refrendo = $refrendos[$s->id] ?? null;
            $articulo = null;
            if ($refrendo?->inciso_articulo_14 !== null) {
                $articulo = $this->cat['articulos'][$refrendo->inciso_articulo_14] ?? null;
                if ($articulo === null) {
                    $errores[] = "inciso '{$refrendo->inciso_articulo_14}' fuera del catálogo";
                }
            }

            if ($errores !== []) {
                $this->incidencia('SOLICITUD_INVALIDA', true, 'solicitudes_certificacion', $s->id, implode('; ', $errores).'.');
                continue;
            }

            $art14 = $s->tipo_tramite === 'refrendo' && (bool) ($refrendo?->aplica_articulo_14);
            $numero = null;
            if ($s->certificado_historico_id !== null) {
                $n = $this->norm->numeroCertificado($certificados[$s->certificado_historico_id] ?? null);
                $numero = $n->revision ? null : $n->valor;
            }

            $this->agregar('solicitud', [
                'soli_id_solicitud' => $s->id, 'soli_id_persona' => $persona,
                'soli_id_area_certificacion' => $catalogo['area_certificacion'],
                'soli_id_tipo_personal_academico' => $catalogo['tipo_personal'],
                'soli_id_convocatoria' => null, 'soli_id_articulo' => $articulo,
                'soli_numero_certificado' => $numero,
                'soli_fecha_registro_solicitud' => $this->local($s->created_at)?->toDateString(),
                'soli_folio_solicitud' => $s->id,
                'solo_art13' => $s->tipo_tramite === 'refrendo' && $refrendo !== null && !$art14,
                'soli_art14' => $art14,
                'soli_tipo_tramite' => $s->tipo_tramite, 'soli_certificacion' => $s->certificacion,
                'soli_id_historico_importacion' => $s->certificado_historico_id,
                'soli_creado_en' => $s->created_at, 'soli_actualizado_en' => $s->updated_at,
            ]);

            // F4: datos propios de la solicitud de refrendo (lo que no cubre SOLICITUD).
            if ($refrendo !== null) {
                $this->agregar('solicitud_refrendo', [
                    'sore_id_solicitud_refrendo' => $refrendo->id, 'sore_id_solicitud' => $s->id,
                    'sore_numero_refrendo_asignado' => $refrendo->numero_refrendo_asignado,
                    'sore_refrendo_actual' => $refrendo->refrendo_actual,
                    'sore_creado_en' => $refrendo->created_at, 'sore_actualizado_en' => $refrendo->updated_at,
                ]);
            }
            $this->incluidos['solicitud'][$s->id] = $persona;

            // Eventos (D-3): borrador, formación autorizada, compromiso, envío.
            $formacion = DB::table('public.formaciones_academicas_solicitud')->where('solicitud_id', $s->id)->first();
            $eventos = ['BO' => $s->created_at];
            if ($formacion?->formacion_completada_at !== null) {
                $eventos['FA'] = $formacion->formacion_completada_at;
            }
            if ($s->compromiso_aceptado) {
                $eventos['CA'] = $s->compromiso_aceptado_en;
            }
            if ($s->estado === 'enviado') {
                $eventos['EN'] = $s->fecha_envio;
            }
            foreach ($eventos as $siglas => $marca) {
                $local = $this->local($marca);
                $this->agregar('estado_solicitud', [
                    'esso_id_c_estado_solicitud' => (int) $this->cat['estados_solicitud'][$estados[$siglas]],
                    'esso_id_solicitud' => $s->id, 'esso_descripcion' => $estados[$siglas], 'esso_siglas' => $siglas,
                    'esso_fecha' => $local->toDateString(), 'esso_hora' => $local->format('H:i:s'), 'esso_motivo_rechazo' => null,
                ]);
            }

            $this->formacion($s, $persona, $formacion);
            $this->documentos($s, $formacion);
        }
    }

    private function formacion(object $s, int $persona, ?object $f): void
    {
        if ($f === null) {
            return;
        }

        // F4: registro de la formación del trámite, con la institución elegida en ESE trámite.
        $institucion = $f->institucion_afiliada_id;
        if ($institucion !== null && !isset($this->incluidos['institucion'][$institucion])) {
            $institucion = null; // la incidencia D-11 ya se reporta en la persona
        }
        $this->agregar('formacion_solicitud', [
            'fosc_id_formacion' => $f->id, 'fosc_id_solicitud' => $s->id, 'fosc_id_institucion' => $institucion,
            'fosc_creado_en' => $f->created_at, 'fosc_actualizado_en' => $f->updated_at,
        ]);

        foreach (['ORIGEN' => ['nivel_origen', 'titulo_origen', 'titulo_origen_otro', 'titulo_origen_archivo', 'cedula_origen_archivo'],
                  'ULTIMO' => ['nivel_ultimo_grado', 'titulo_ultimo_grado', 'titulo_ultimo_grado_otro', 'titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo']]
                 as $tipo => [$cNivel, $cTitulo, $cOtro, $cTituloArch, $cCedulaArch]) {
            if (blank($f->{$cNivel}) || blank($f->{$cTitulo})) {
                $this->incidencia('FORMACION_INCOMPLETA', false, 'formaciones_academicas_solicitud', $f->id, "{$tipo}: nivel o título sin capturar; no hay estudio que registrar todavía.");
                continue;
            }

            $nivel = $this->cat['niveles'][$f->{$cNivel}] ?? null;
            if ($nivel === null) {
                $this->incidencia('NIVEL_FUERA_DE_CATALOGO', true, 'formaciones_academicas_solicitud', $f->id, "{$tipo}: nivel '{$f->{$cNivel}}' fuera del catálogo.");
                continue;
            }

            $nombre = $f->{$cTitulo};
            $estado = 'ACT';
            if ($nombre === 'Otro') {
                $nombre = trim((string) $f->{$cOtro});
                $estado = 'REV';
                if ($nombre === '') {
                    // Registro histórico incompleto: se conserva literalmente el
                    // único valor existente en public ("Otro"), sin inventar título.
                    // El estudio queda REV; las capturas nuevas siguen sujetas a
                    // required_if en FormacionAcademicaController.
                    $nombre = 'Otro';
                    $this->incidencia('OTRO_SIN_TEXTO', false, 'formaciones_academicas_solicitud', $f->id,
                        "{$tipo}: descripción histórica ausente; se conserva «Otro» en revisión (REV), sin atribuir un título.");
                }
            } elseif (!isset($this->cat['estudios'][$nivel][$nombre])) {
                $estado = 'REV';
                $this->incidencia('TITULO_FUERA_DE_CATALOGO', false, 'formaciones_academicas_solicitud', $f->id, "{$tipo}: título histórico fuera del catálogo; se registra en revisión (REV).");
            }

            $excede = $this->largoExcedido('formaciones_academicas_solicitud', $f->id, $cOtro, $nombre, 150)
                | $this->largoExcedido('formaciones_academicas_solicitud', $f->id, $cTituloArch, $f->{$cTituloArch}, 300)
                | $this->largoExcedido('formaciones_academicas_solicitud', $f->id, $cCedulaArch, $f->{$cCedulaArch}, 180);
            if ($excede) {
                continue;
            }

            $this->agregar('persona_estudio_profesional', [
                'pees_id_persona' => $persona, 'pees_id_solicitud' => $s->id, 'pees_tipo' => $tipo,
                'espr_id_nivel_profesional' => $nivel, 'espr_nombre' => $nombre, 'espr_estado' => $estado,
                'pees_url_titulo' => $f->{$cTituloArch}, 'pees_cedula' => null, 'pees_url_cedula' => $f->{$cCedulaArch},
            ]);
        }
    }

    private function documentos(object $s, ?object $f): void
    {
        $catalogo = (array) config('anfeca_pdm.catalogos.documentos');
        $cargado = (int) $this->cat['estados_documento'][config('anfeca_pdm.catalogos.estados_documento.CARGADO')];
        $sustituido = (int) $this->cat['estados_documento'][config('anfeca_pdm.catalogos.estados_documento.SUSTITUIDO')];
        $p = DB::table('public.documentos_probatorios_solicitud')->where('solicitud_id', $s->id)->first();

        // F4: registro de documentos probatorios del trámite.
        if ($p !== null) {
            $this->agregar('documentos_solicitud', [
                'dosc_id_documentos' => $p->id, 'dosc_id_solicitud' => $s->id,
                'dosc_creado_en' => $p->created_at, 'dosc_actualizado_en' => $p->updated_at,
            ]);
        }

        $vigentes = [];
        foreach (['titulo_origen_archivo', 'cedula_origen_archivo', 'titulo_ultimo_grado_archivo', 'cedula_ultimo_grado_archivo'] as $campo) {
            if (filled($f?->{$campo})) {
                $vigentes[] = [$campo, $f->{$campo}, $f->updated_at ?? $f->created_at];
            }
        }
        foreach (['constancia_antiguedad_archivo', 'comprobante_pago_archivo', 'solicitud_articulo_14_archivo', 'constancia_articulo_14_archivo', 'link'] as $campo) {
            if (filled($p?->{$campo})) {
                $vigentes[] = [$campo, $p->{$campo}, $p->updated_at ?? $p->created_at];
            }
        }

        $historial = DB::table('public.historial_documentos_academicos')->where('solicitud_id', $s->id)->orderBy('id')->get();

        $filas = [];
        foreach ($vigentes as [$campo, $ruta, $marca]) {
            $filas[] = [$campo, $ruta, $marca, $cargado, 'Archivo vigente', 'solicitudes_certificacion', $s->id];
        }
        foreach ($historial as $h) {
            $filas[] = [$h->tipo_documento, $h->ruta_archivo, $h->created_at, $sustituido, 'Versión anterior conservada (historial)', 'historial_documentos_academicos', $h->id];
        }

        foreach ($filas as [$campo, $ruta, $marca, $estado, $observacion, $tablaOrigen, $idOrigen]) {
            if (!isset($catalogo[$campo])) {
                $this->incidencia('DOCUMENTO_FUERA_DE_CATALOGO', true, $tablaOrigen, $idOrigen, "Tipo de documento '{$campo}' fuera del catálogo.");
                continue;
            }
            if ($this->largoExcedido($tablaOrigen, $idOrigen, $campo, $ruta, 500)) {
                continue;
            }
            $local = $this->local($marca ?? $s->created_at);
            $this->agregar('solicitud_documento', [
                'socu_id_solicitud' => $s->id, 'docu_id_documento' => (int) $this->cat['documentos'][$catalogo[$campo]],
                'socu_url_archivo' => $ruta, 'socu_fecha' => $local->toDateString(), 'socu_hora' => $local->format('H:i:s'),
                'esdo_id_estado_documento' => $estado, 'esdo_observacion' => $observacion,
            ]);
        }

        if (filled($p?->comprobante_pago_archivo) && !$this->largoExcedido('documentos_probatorios_solicitud', $p->id, 'comprobante_pago_archivo', $p->comprobante_pago_archivo, 255)) {
            $this->agregar('pago', ['pago_id_solicitud' => $s->id, 'pago_url_comprobante' => $p->comprobante_pago_archivo]);
        }
    }
}
