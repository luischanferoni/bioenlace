<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "chat_messages".
 *
 * @property int $id
 * @property int $consulta_id
 * @property int $user_id
 * @property string $content
 * @property string $created_at
 *
 * @property Consulta $consulta
 * @property User $user
 */
class ChatMessage extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'chat_mensaje';
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'dialogo_id' => 'Diálogo ID',
            'sender_id' => 'Sender ID',
            'sender_name' => 'Sender Name',
            'content' => 'Content',
            'timestamp' => 'Timestamp',
            'status' => 'Status',
            'message_type' => 'Message Type',
            'is_resent' => 'Is Resent',
            'original_message_id' => 'Original Message ID',
            'metadata' => 'Metadata',
        ];
    }

    /**
     * Gets query for [[Dialogo]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDialogo()
    {
        return $this->hasOne(Dialogo::class, ['id' => 'dialogo_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->timestamp = date('Y-m-d H:i:s');
                $this->status = 'enviado';
                $this->message_type = 'texto';
                $this->is_resent = 0;
            }
            return true;
        }
        return false;
    }
}