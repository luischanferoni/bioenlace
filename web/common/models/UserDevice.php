<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Dispositivo de usuario (Didit + push).
 *
 * @property int $id
 * @property int|null $id_persona
 * @property int|null $id_user
 * @property string $device_id
 * @property string|null $platform
 * @property string|null $push_token
 * @property string|null $push_provider
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
     * Registra o actualiza token push para persona.
     */
    public static function upsertPushToken($idPersona, $deviceId, $pushToken, $provider, $platform = null)
    {
        $model = static::find()
            ->where(['device_id' => $deviceId])
            ->andWhere(['id_persona' => (int) $idPersona])
            ->one();
        if (!$model) {
            $model = new static();
            $model->device_id = $deviceId;
            $model->id_persona = (int) $idPersona;
            if (Yii::$app->user && !Yii::$app->user->isGuest) {
                $model->id_user = Yii::$app->user->id;
            }
        }
        $model->push_token = $pushToken;
        $model->push_provider = $provider;
        if ($platform) {
            $model->platform = $platform;
        }
        $model->is_active = true;
        return $model->save(false);
    }
}
