<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Casos de triage (códigos internos) permitidos para teleconsulta en un servicio con política `algunas`.
 *
 * @property int $id
 * @property int $id_servicio
 * @property string $caso_codigo
 * @property string $created_at
 */
class ServicioTeleconsultaCaso extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%servicio_teleconsulta_caso}}';
    }

    public function rules()
    {
        return [
            [['id_servicio', 'caso_codigo'], 'required'],
            [['id_servicio'], 'integer'],
            [['caso_codigo'], 'string', 'max' => 64],
            [['created_at'], 'safe'],
            [
                ['id_servicio'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Servicio::class,
                'targetAttribute' => ['id_servicio' => 'id_servicio'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function listCodigosPorServicio(int $idServicio): array
    {
        if ($idServicio <= 0) {
            return [];
        }
        $rows = static::find()
            ->select(['caso_codigo'])
            ->where(['id_servicio' => $idServicio])
            ->column();

        return array_values(array_filter(array_map('strval', $rows)));
    }
}
