<?php

namespace common\models;

use yii\db\ActiveRecord;

class AsistenteConversacion extends ActiveRecord
{
    public static function tableName()
    {
        return 'asistente_conversacion';
    }

    public function rules()
    {
        return [
            [['usuario_id', 'bot_id'], 'required'],
            [['usuario_id', 'bot_id'], 'string', 'max' => 36],
            [['created_at', 'updated_at'], 'safe'],
            [['contexto_json'], 'safe'],
            [['usuario_id', 'bot_id'], 'unique', 'targetAttribute' => ['usuario_id', 'bot_id'], 'message' => 'Ya existe una conversación entre este usuario y este bot.'],
        ];
    }

    public function getInteracciones()
    {
        return $this->hasMany(AsistenteInteraccion::class, ['conversacion_id' => 'id'])->orderBy(['created_at' => SORT_ASC]);
    }
}

