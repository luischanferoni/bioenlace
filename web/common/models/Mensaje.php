<?php
// models/Mensaje.php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

class Mensaje extends ActiveRecord
{
    public static function tableName()
    {
        return 'chat_mensaje';
    }

    public function rules()
    {
        return [
            [['dialogo_id', 'sender_id', 'content'], 'required'],
            [['dialogo_id', 'original_message_id'], 'integer'],
            [['content'], 'string'],
            [['timestamp'], 'safe'],
            [['is_resent'], 'boolean'],
            [['status'], 'in', 'range' => ['recibido', 'enviado', 'error']],
            [['message_type'], 'in', 'range' => ['texto', 'imagen', 'audio', 'otro']],
            [['metadata'], 'safe'],
            [['sender_id'], 'string', 'max' => 36],
            [['sender_name'], 'string', 'max' => 100],
            [['dialogo_id'], 'exist', 'skipOnError' => true, 'targetClass' => Dialogo::class, 'targetAttribute' => ['dialogo_id' => 'id']],
        ];
    }

    public function getDialogo()
    {
        return $this->hasOne(Dialogo::class, ['id' => 'dialogo_id']);
    }
}
