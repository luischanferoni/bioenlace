<?php

namespace common\models;

use yii\db\ActiveRecord;

class AsistenteInteraccion extends ActiveRecord
{
    public static function tableName()
    {
        return 'asistente_interaccion';
    }

    public function rules()
    {
        return [
            [['conversacion_id', 'sender_id', 'texto'], 'required'],
            [['conversacion_id', 'original_message_id'], 'integer'],
            [['texto'], 'string'],
            [['created_at'], 'safe'],
            [['is_resent'], 'boolean'],
            [['metadata'], 'safe'],
            [['sender_id'], 'string', 'max' => 36],
            [['sender_name'], 'string', 'max' => 100],
            [['status'], 'in', 'range' => ['recibido', 'enviado', 'error']],
            [['message_type'], 'in', 'range' => ['texto', 'imagen', 'audio', 'otro']],
            [['conversacion_id'], 'exist', 'skipOnError' => true, 'targetClass' => AsistenteConversacion::class, 'targetAttribute' => ['conversacion_id' => 'id']],
        ];
    }

    public function getConversacion()
    {
        return $this->hasOne(AsistenteConversacion::class, ['id' => 'conversacion_id']);
    }
}

