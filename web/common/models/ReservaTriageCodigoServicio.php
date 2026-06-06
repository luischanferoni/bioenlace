<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Regla declarativa: código de triage de reserva → fila(s) de {@see Servicio}.
 *
 * @property int $id
 * @property string $triage_codigo
 * @property int $id_servicio
 * @property int $prioridad
 * @property string|null $notas
 */
class ReservaTriageCodigoServicio extends ActiveRecord
{
    /** @var array<string, list<int>>|null codigo => id_servicio[] */
    private static ?array $cachePorCodigo = null;

    public static function tableName(): string
    {
        return '{{%reserva_triage_codigo_servicio}}';
    }

    public function rules(): array
    {
        return [
            [['triage_codigo', 'id_servicio'], 'required'],
            [['id_servicio', 'prioridad'], 'integer'],
            [['notas'], 'string'],
            [['triage_codigo'], 'string', 'max' => 64],
            [['id_servicio'], 'exist', 'targetClass' => Servicio::class, 'targetAttribute' => 'id_servicio'],
        ];
    }

    /**
     * @return list<int>
     */
    public static function idsParaCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }
        self::warmCache();

        return self::$cachePorCodigo[$codigo] ?? [];
    }

    /**
     * Unión de servicios sugeridos por varios códigos del draft (1, 2 o más especialidades).
     *
     * @param list<string> $codigos
     * @return list<int>
     */
    public static function idsParaCodigos(array $codigos): array
    {
        $out = [];
        foreach ($codigos as $codigo) {
            if (!is_string($codigo)) {
                continue;
            }
            foreach (self::idsParaCodigo($codigo) as $id) {
                $out[] = $id;
            }
        }
        sort($out);

        return array_values(array_unique($out));
    }

    public static function resetCache(): void
    {
        self::$cachePorCodigo = null;
    }

    private static function warmCache(): void
    {
        if (self::$cachePorCodigo !== null) {
            return;
        }
        self::$cachePorCodigo = [];
        try {
            /** @var self[] $rows */
            $rows = static::find()
                ->orderBy(['prioridad' => SORT_DESC, 'triage_codigo' => SORT_ASC, 'id_servicio' => SORT_ASC])
                ->all();
            foreach ($rows as $row) {
                $code = trim((string) $row->triage_codigo);
                $id = (int) $row->id_servicio;
                if ($code === '' || $id <= 0) {
                    continue;
                }
                self::$cachePorCodigo[$code] ??= [];
                if (!in_array($id, self::$cachePorCodigo[$code], true)) {
                    self::$cachePorCodigo[$code][] = $id;
                }
            }
        } catch (\Throwable $e) {
            self::$cachePorCodigo = [];
        }
    }
}
