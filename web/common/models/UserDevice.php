<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Dispositivo de usuario (Didit + push).
 *
 * La identidad push operativa es `push_token` (único). `device_id` se conserva
 * para flujos biométricos / legado, no como clave de registro push.
 *
 * @property int $id
 * @property int|null $id_persona
 * @property int|null $id_user
 * @property string $device_id
 * @property string|null $platform
 * @property string|null $push_token
 * @property string|null $push_provider
 * @property bool|int|null $is_active
 */
class UserDevice extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%user_device}}';
    }

    public function rules()
    {
        return [
            [['device_id'], 'required'],
            [['id_persona', 'id_user'], 'integer'],
            [['device_id'], 'string', 'max' => 191],
            [['push_token'], 'string', 'max' => 512],
            [['push_provider', 'platform'], 'string', 'max' => 50],
            [['is_active'], 'boolean'],
        ];
    }

    /**
     * Registra o reasigna token push a la persona autenticada.
     * Un mismo `push_token` queda activo para una sola persona.
     */
    public static function upsertPushToken($idPersona, $pushToken, $provider, $platform = null, $deviceId = null)
    {
        $idPersona = (int) $idPersona;
        $pushToken = trim((string) $pushToken);
        if ($idPersona <= 0 || $pushToken === '') {
            return false;
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $model = static::find()->where(['push_token' => $pushToken])->one();
            if (!$model) {
                $model = new static();
                $model->push_token = $pushToken;
            }
            $model->id_persona = $idPersona;
            if (Yii::$app->user && !Yii::$app->user->isGuest) {
                $model->id_user = Yii::$app->user->id;
            }
            $deviceId = $deviceId !== null ? trim((string) $deviceId) : '';
            if ($deviceId !== '') {
                $model->device_id = $deviceId;
            } elseif (empty($model->device_id)) {
                $model->device_id = 'push:' . substr(hash('sha256', $pushToken), 0, 40);
            }
            $model->push_provider = $provider ?: 'fcm';
            if ($platform) {
                $model->platform = $platform;
            }
            $model->is_active = true;
            if (!$model->save(false)) {
                $tx->rollBack();
                return false;
            }

            // Desactiva otras filas del mismo token (defensa ante carreras previas a UNIQUE).
            static::updateAll(
                ['is_active' => false],
                [
                    'and',
                    ['push_token' => $pushToken],
                    ['<>', 'id', (int) $model->id],
                ]
            );

            $tx->commit();
            return true;
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('upsertPushToken: ' . $e->getMessage(), 'user-device');
            return false;
        }
    }
}
