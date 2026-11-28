<?php

namespace App\Services\Catalogos;

use Illuminate\Support\Facades\DB;

/**
 * Programas académicos ANUIES ya cargados en el modelo oficial (ESTUDIO_PROFESIONAL con ESPR_ESTADO = 'ACT',
 * nivel en NIVEL_PROFESIONAL). Consulta directa a PostgreSQL, sin JSON ni columnas fuera del modelo:
 * no usa estudio_profesional.espr_id_area_certificacion (P-03, no autorizada).
 *
 * El modelo oficial no relaciona programas con AREA_CERTIFICACION (solo SOLICITUD lo hace): la búsqueda es por
 * nivel y texto. El área de certificación sigue siendo la de la solicitud y es la que rige las reglas de elegibilidad.
 */
final class ProgramasAnuiesPostgres
{
    public const ESTADO = 'ACT';
    private const DE = 'ÁÀÄÂÉÈËÊÍÌÏÎÓÒÖÔÚÙÜÛÑáàäâéèëêíìïîóòöôúùüûñ';
    private const A = 'AAAAEEEEIIIIOOOOUUUUNaaaaeeeeiiiioooouuuun';

    private static function normalizarSql(string $expr): string
    {
        return "lower(translate({$expr}, '".self::DE."', '".self::A."'))";
    }

    public static function normalizar(string $texto): string
    {
        $mapa = array_combine(mb_str_split(self::DE), str_split(self::A));

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(strtr($texto, $mapa))));
    }

    /**
     * @param list<string> $niveles  descripciones de NIVEL_PROFESIONAL (p. ej. ['Licenciatura'] o ['Maestría'])
     * @return array{total:int, programas:list<array{id:int, nombre:string, nivel:string}>}
     */
    public function buscar(array $niveles, string $texto, int $limite = 60): array
    {
        $palabras = array_values(array_filter(explode(' ', self::normalizar($texto)), fn ($p) => mb_strlen($p) >= 2));
        $nivelesNorm = array_map(self::normalizar(...), $niveles);
        $q = DB::table('estudio_profesional as e')
            ->join('nivel_profesional as n', 'n.nipr_id_nivel_profesional', '=', 'e.espr_id_nivel_profesional')
            ->where('e.espr_estado', self::ESTADO)
            ->whereIn(DB::raw(self::normalizarSql('n.nipr_nivel_profesional')), $nivelesNorm);
        foreach ($palabras as $p) {
            $q->whereRaw(self::normalizarSql('e.espr_nombre')." like ?", ['%'.addcslashes($p, '%_\\').'%']);
        }
        $total = (clone $q)->count();
        $filas = $q->orderBy('e.espr_nombre')->orderBy('e.espr_id_estudio_profesional')->limit($limite)
            ->get(['e.espr_id_estudio_profesional as id', 'e.espr_nombre as nombre', 'n.nipr_nivel_profesional as nivel']);

        return ['total' => $total, 'programas' => $filas->map(fn ($f) => ['id' => (int) $f->id, 'nombre' => $f->nombre, 'nivel' => $f->nivel])->all()];
    }

    /** Validación del servidor: el ID elegido existe, es ANUIES (ACT) y pertenece a uno de los niveles permitidos. */
    public function validar(int $id, array $niveles): ?array
    {
        $f = DB::table('estudio_profesional as e')
            ->join('nivel_profesional as n', 'n.nipr_id_nivel_profesional', '=', 'e.espr_id_nivel_profesional')
            ->where('e.espr_id_estudio_profesional', $id)->where('e.espr_estado', self::ESTADO)
            ->first(['e.espr_id_estudio_profesional as id', 'e.espr_nombre as nombre', 'n.nipr_nivel_profesional as nivel']);
        if (!$f || !in_array(self::normalizar($f->nivel), array_map(self::normalizar(...), $niveles), true)) {
            return null;
        }

        return ['id' => (int) $f->id, 'nombre' => $f->nombre, 'nivel' => $f->nivel];
    }
}
