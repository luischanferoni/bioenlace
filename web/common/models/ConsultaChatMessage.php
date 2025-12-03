<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "consulta_chat_messages".
 * Tabla separada para mensajes de chat médico en consultas.
 *
 * @property int $id
 * @property int $consulta_id
 * @property int $user_id
 * @property string $user_name
 * @property string $user_role
 * @property string $content
 * @property string $message_type
 * @property boolean $is_read
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Consulta $consulta
 * @property User $user
 */
class ConsultaChatMessage extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consulta_chat_messages';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['consulta_id', 'user_id', 'user_name', 'user_role', 'content'], 'required'],
            [['consulta_id', 'user_id'], 'integer'],
            [['content'], 'string'],
            [['is_read'], 'boolean'],
            [['created_at', 'updated_at'], 'safe'],
            [['user_name'], 'string', 'max' => 100],
            [['user_role'], 'string', 'max' => 20],
            [['message_type'], 'string', 'max' => 20],
            [['message_type'], 'in', 'range' => ['texto', 'imagen', 'audio', 'documento']],
            [['user_role'], 'in', 'range' => ['medico', 'paciente', 'enfermeria', 'administrador']],
            [['consulta_id'], 'exist', 'skipOnError' => true, 'targetClass' => Consulta::class, 'targetAttribute' => ['consulta_id' => 'id_consulta']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'consulta_id' => 'Consulta ID',
            'user_id' => 'User ID',
            'user_name' => 'User Name',
            'user_role' => 'User Role',
            'content' => 'Content',
            'message_type' => 'Message Type',
            'is_read' => 'Is Read',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for [[Consulta]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasOne(Consulta::class, ['id_consulta' => 'consulta_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * Marcar mensaje como leído
     */
    public function markAsRead()
    {
        $this->is_read = true;
        return $this->save();
    }

    /**
     * Obtener mensajes no leídos de una consulta
     */
    public static function getUnreadMessages($consultaId, $userRole = null)
    {
        $query = self::find()
            ->where(['consulta_id' => $consultaId, 'is_read' => false]);
        
        if ($userRole) {
            $query->andWhere(['!=', 'user_role', $userRole]);
        }
        
        return $query->all();
    }
}
