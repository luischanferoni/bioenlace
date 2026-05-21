<?php

namespace common\components\Core\Service\Push;

use Yii;

/**
 * Config FCM en params (`fcmPush`). Legado: `turnosPush` (deprecado).
 */
final class FcmPushConfig
{
    public const LOG_CATEGORY = 'fcm-push';

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $params = Yii::$app->params;
        if (!empty($params['fcmPush']) && is_array($params['fcmPush'])) {
            return $params['fcmPush'];
        }
        if (!empty($params['turnosPush']) && is_array($params['turnosPush'])) {
            return $params['turnosPush'];
        }

        return [];
    }
}
