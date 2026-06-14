<?php

namespace common\models;

use common\components\Domain\Clinical\Service\SecureMediaService;
use common\models\Clinical\Encounter;
use Yii;
use yii\db\ActiveRecord;

/**
 * Mensajes pre-consulta — tabla `interaccion_motivos_consulta`.
 *
 * @property int $id
 * @property int $encounter_id
 * @property int $user_id
 * @property string $user_name
 * @property string $texto
 * @property string $message_type
 * @property string $created_at
 *
 * @property Encounter $encounter
 */
class ConsultaMotivosMessage extends ActiveRecord
{
    const TYPE_TEXTO = 'texto';
    const TYPE_IMAGEN = 'imagen';
    const TYPE_AUDIO = 'audio';

    public static function tableName(): string
    {
        return 'interaccion_motivos_consulta';
    }

    /** Compat API: `consulta_id` = `encounter_id`. */
    public function getConsulta_id(): int
    {
        return (int) $this->encounter_id;
    }

    public function setConsulta_id($value): void
    {
        $this->encounter_id = (int) $value;
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'user_id', 'user_name', 'texto'], 'required'],
            [['encounter_id', 'user_id'], 'integer'],
            [['texto'], 'string'],
            [['created_at'], 'safe'],
            [['user_name'], 'string', 'max' => 100],
            [['message_type'], 'string', 'max' => 20],
            [['message_type'], 'in', 'range' => [self::TYPE_TEXTO, self::TYPE_IMAGEN, self::TYPE_AUDIO]],
            [['encounter_id'], 'exist', 'skipOnError' => true, 'targetClass' => Encounter::class, 'targetAttribute' => ['encounter_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    /** @deprecated use {@see getEncounter()} */
    public function getConsulta(): \yii\db\ActiveQuery
    {
        return $this->getEncounter();
    }

    /**
     * @param self[] $messages
     * @return list<array<string, mixed>>
     */
    public static function serializeForApi(array $messages, ?string $hostWithWebAlias = null): array
    {
        unset($hostWithWebAlias);
        $out = [];
        foreach ($messages as $message) {
            $content = (string) $message->texto;
            if (in_array($message->message_type, [self::TYPE_IMAGEN, self::TYPE_AUDIO], true)
                && $content !== '') {
                $content = SecureMediaService::contentForApi(
                    SecureMediaService::SCOPE_MOTIVOS_CONSULTA,
                    (int) $message->encounter_id,
                    $content
                );
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

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert) {
            $this->created_at = date('Y-m-d H:i:s');
            $this->message_type = $this->message_type ?: self::TYPE_TEXTO;
        }

        return true;
    }
}
