<?php

// models/Dialogo.php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

class Dialogo extends ActiveRecord
{
    public static function tableName()
    {
        return 'chat_dialogo';
    }

    public function rules()
    {
        return [
            [['usuario_id', 'bot_id'], 'required'],
            [['usuario_id', 'bot_id'], 'string', 'max' => 36],
            [['fecha_inicio', 'fecha_ultima_interaccion'], 'safe'],
            [['usuario_id', 'bot_id'], 'unique', 'targetAttribute' => ['usuario_id', 'bot_id'], 'message' => 'Ya existe un diálogo entre este usuario y este bot.'],
        ];
    }

    public function getMensajes()
    {
        return $this->hasMany(Mensaje::class, ['dialogo_id' => 'id'])->orderBy(['timestamp' => SORT_ASC]);
    }
}
