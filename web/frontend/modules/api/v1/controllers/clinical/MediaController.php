<?php

namespace frontend\modules\api\v1\controllers\clinical;

use frontend\modules\api\v1\controllers\BaseController;
use common\components\Domain\Clinical\Encounter\Application\Service\SecureMediaService;
use Yii;
use yii\web\Controller;

/**
 * GET /api/v1/media/{scope}/{encounter_id}/{filename} — archivo binario (Bearer + participantes del encounter).
 *
 * scope: motivos-consulta | consulta-chat
 */
class MediaController extends BaseController
{
    /**
     * No forzar JSON en la descarga binaria.
     */
    public function beforeAction($action)
    {
        if ($action->id === 'ver') {
            return Controller::beforeAction($action);
        }

        return parent::beforeAction($action);
    }

    /**
     * @param string $scope
     * @param int $encounterId
     * @param string $filename
     */
    public function actionVer($scope, $encounterId, $filename)
    {
        $resolved = SecureMediaService::resolveForDownload(
            (string) $scope,
            (int) $encounterId,
            (string) $filename
        );

        Yii::$app->response->format = Response::FORMAT_RAW;

        return Yii::$app->response->sendFile(
            $resolved['path'],
            $resolved['filename'],
            [
                'mimeType' => $resolved['mime'],
                'inline' => true,
            ]
        );
    }
}
