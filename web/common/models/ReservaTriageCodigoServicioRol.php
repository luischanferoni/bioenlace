<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Regla declarativa: código de triage de reserva → rol lógico de servicio.
 *
 * @property int $id
 * @property string $triage_codigo
 * @property string $servicio_rol
 * @property int $prioridad mayor = gana ante empate de especificidad
 * @property string|null $notas
 */
class ReservaTriageCodigoServicioRol extends ActiveRecord
{
    /** @var array<string, string>|null codigo => rol */
    private static ?array $cachePorCodigo = null;

    public static function tableName(): string
    {
        return '{{%reserva_triage_codigo_servicio_rol}}';
    }

    public function rules(): array
    {
        return [
            [['triage_codigo', 'servicio_rol'], 'required'],
            [['prioridad'], 'integer'],
            [['notas'], 'string'],
            [['triage_codigo'], 'string', 'max' => 64],
            [['servicio_rol'], 'string', 'max' => 64],
            [['triage_codigo'], 'unique'],
        ];
    }

    public static function rolParaCodigo(string $codigo): ?string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        self::warmCache();
        if (self::$cachePorCodigo === null) {
            return null;
        }

        return self::$cachePorCodigo[$codigo] ?? null;
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
                ->orderBy(['prioridad' => SORT_DESC, 'triage_codigo' => SORT_ASC])
                ->all();
            foreach ($rows as $row) {
                $code = trim((string) $row->triage_codigo);
                $rol = trim((string) $row->servicio_rol);
                if ($code === '' || $rol === '' || isset(self::$cachePorCodigo[$code])) {
                    continue;
                }
                self::$cachePorCodigo[$code] = $rol;
            }
        } catch (\Throwable $e) {
            self::$cachePorCodigo = [];
        }
    }
}
