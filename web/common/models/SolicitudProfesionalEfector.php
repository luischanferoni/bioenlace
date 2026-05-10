<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Solicitud / pedido entre profesionales del mismo efector.
 *
 * @property int $id
 * @property int $id_efector
 * @property int $id_solicitante_profesional_efector_servicio
 * @property int|null $id_destinatario_profesional_efector_servicio
 * @property int|null $id_intermediario_user
 * @property string $estado
 * @property string $tipo
 * @property string $mensaje
 * @property string $created_at
 * @property string $updated_at
 */
class SolicitudProfesionalEfector extends ActiveRecord
{
    const ESTADO_PENDIENTE = 'PENDIENTE';
    const ESTADO_ASIGNADA = 'ASIGNADA';
    const ESTADO_EN_CURSO = 'EN_CURSO';
    const ESTADO_RESUELTA = 'RESUELTA';
    const ESTADO_RECHAZADA = 'RECHAZADA';

    public static function tableName()
    {
        return '{{%solicitud_profesional_efector}}';
    }

    public function rules()
    {
        return [
            [['id_efector', 'id_solicitante_profesional_efector_servicio', 'mensaje'], 'required'],
            [['id_efector', 'id_solicitante_profesional_efector_servicio', 'id_destinatario_profesional_efector_servicio', 'id_intermediario_user'], 'integer'],
            [['mensaje'], 'string'],
            [['estado'], 'string', 'max' => 32],
            [['tipo'], 'string', 'max' => 64],
        ];
    }

    public function getEventos()
    {
        return $this->hasMany(SolicitudProfesionalEfectorEvento::className(), ['id_solicitud' => 'id'])->orderBy(['id' => SORT_ASC]);
    }
}
