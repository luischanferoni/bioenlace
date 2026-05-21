<?php

namespace common\models;

use common\models\Clinical\Encounter;
use Yii;
use yii\db\ActiveRecord;

/**
 * Mensajes de chat clínico — tabla `interaccion_chat_clinico`.
 *
 * @property int $id
 * @property int $encounter_id
 * @property int $user_id
 * @property string $user_name
 * @property string $user_role
 * @property string $texto
 * @property string $message_type
 * @property bool $is_read
 * @property string $created_at
 * @property string $updated_at
 */
class ConsultaChatMessage extends ActiveRecord
{
    public static function tableName()
    {
        return 'interaccion_chat_clinico';
    }

    public function rules()
    {
        return [
            [['encounter_id', 'user_id', 'user_name', 'user_role', 'texto'], 'required'],
            [['encounter_id', 'user_id'], 'integer'],
            [['texto'], 'string'],
            [['is_read'], 'boolean'],
            [['created_at', 'updated_at'], 'safe'],
            [['user_name'], 'string', 'max' => 100],
            [['user_role'], 'string', 'max' => 20],
            [['message_type'], 'string', 'max' => 20],
            [['message_type'], 'in', 'range' => ['texto', 'imagen', 'audio', 'video', 'documento']],
            [['user_role'], 'in', 'range' => ['medico', 'paciente', 'enfermeria', 'administrador']],
            [['encounter_id'], 'exist', 'skipOnError' => true, 'targetClass' => Encounter::class, 'targetAttribute' => ['encounter_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function requeridosPrompt()
    {
        return [];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'encounter_id' => 'Encounter ID',
            'user_id' => 'User ID',
            'user_name' => 'User Name',
            'user_role' => 'User Role',
            'texto' => 'Texto',
            'message_type' => 'Message Type',
            'is_read' => 'Is Read',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public function getEncounter()
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /** Alias de lectura para clientes que usan `content`. */
    public function getContent(): string
    {
        return (string) $this->texto;
    }

    public function setContent($value): void
    {
        $this->texto = (string) $value;
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->created_at = date('Y-m-d H:i:s');
                $this->message_type = $this->message_type ?: 'texto';
                $this->is_read = $this->is_read ?: false;
            }
            $this->updated_at = date('Y-m-d H:i:s');

            return true;
        }

        return false;
    }

    public function markAsRead()
    {
        $this->is_read = true;

        return $this->save();
    }

    public static function getUnreadMessages($encounterId, $userRole = null)
    {
        $query = self::find()
            ->where(['encounter_id' => $encounterId, 'is_read' => false]);

        if ($userRole) {
            $query->andWhere(['!=', 'user_role', $userRole]);
        }

        return $query->all();
    }
}
