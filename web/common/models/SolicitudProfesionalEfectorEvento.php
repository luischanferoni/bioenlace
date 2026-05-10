<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Evento / auditoría de {@see SolicitudProfesionalEfector}.
 *
 * @property int $id
 * @property int $id_solicitud
 * @property int|null $id_user
 * @property string $tipo
 * @property string|null $detalle
 * @property string $created_at
 */
class SolicitudProfesionalEfectorEvento extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%solicitud_profesional_efector_evento}}';
    }
}
