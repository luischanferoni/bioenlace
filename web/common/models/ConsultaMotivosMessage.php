<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Mensajes de la conversación de motivos de consulta (paciente envía texto, audio, fotos).
 * Un proceso posterior en el backend codifica, corrige ortografía y estructura el contenido
 * en Consulta.motivo_consulta y ConsultaMotivos.
 *
 * @property int $id
 * @property int $consulta_id
 * @property int $user_id
 * @property string $user_name
 * @property string $texto Texto del mensaje o ruta relativa (imagen/audio)
 * @property string $message_type texto|imagen|audio
 * @property string $created_at
 *
 * @property Consulta $consulta
 * @property User $user
 */
class ConsultaMotivosMessage extends ActiveRecord
{
    const TYPE_TEXTO = 'texto';
    const TYPE_IMAGEN = 'imagen';
    const TYPE_AUDIO = 'audio';

    public static function tableName()
    {
        return 'interaccion_motivos_consulta';
    }

    public function rules()
    {
        return [
            [['consulta_id', 'user_id', 'user_name', 'texto'], 'required'],
            [['consulta_id', 'user_id'], 'integer'],
            [['texto'], 'string'],
            [['created_at'], 'safe'],
            [['user_name'], 'string', 'max' => 100],
            [['message_type'], 'string', 'max' => 20],
            [['message_type'], 'in', 'range' => [self::TYPE_TEXTO, self::TYPE_IMAGEN, self::TYPE_AUDIO]],
            [['consulta_id'], 'exist', 'skipOnError' => true, 'targetClass' => Consulta::class, 'targetAttribute' => ['consulta_id' => 'id_consulta']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'consulta_id' => 'Consulta ID',
            'user_id' => 'User ID',
            'user_name' => 'User Name',
            'texto' => 'Texto',
            'message_type' => 'Message Type',
            'created_at' => 'Created At',
        ];
    }

    public function getConsulta()
    {
        return $this->hasOne(Consulta::class, ['id_consulta' => 'consulta_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Serializa mensajes como en la API `motivos-consulta/mensajes` (content con URL absoluta si aplica).
     *
     * @param self[] $messages
     * @return list<array{id:int, content:string, user_id:int, user_name:string, message_type:string, created_at:string}>
     */
    public static function serializeForApi(array $messages, string $hostWithWebAlias): array
    {
        $baseUrl = rtrim($hostWithWebAlias, '/');
        $out = [];
        foreach ($messages as $message) {
            $content = (string) $message->texto;
            if (in_array($message->message_type, [self::TYPE_IMAGEN, self::TYPE_AUDIO], true)
                && $content !== ''
                && strpos($content, 'http') !== 0) {
                $content = $baseUrl . '/' . ltrim($content, '/');
            }
            $out[] = [
                'id' => (int) $message->id,
                'content' => $content,
                'user_id' => (int) $message->user_id,
                'user_name' => (string) $message->user_name,
                'message_type' => $message->message_type ?: self::TYPE_TEXTO,
                'created_at' => (string) $message->created_at,
            ];
        }

        return $out;
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->created_at = date('Y-m-d H:i:s');
                $this->message_type = $this->message_type ?: self::TYPE_TEXTO;
            }
            return true;
        }
        return false;
    }
}
