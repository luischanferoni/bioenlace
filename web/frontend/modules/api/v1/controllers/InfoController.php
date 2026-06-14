<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Platform\Core\DataAccess\DataAccessUiService;
use common\components\Platform\Core\Permission\Domain\ApiDomainOperationBridge;

/**
 * Consultas staff agregadas / informativas (métricas DataAccess).
 */
class InfoController extends BaseController
{
    /**
     * Ejecuta una métrica registrada y devuelve ui_json informativo.
     *
     * GET|POST /api/v1/info
     *
     * Parámetros: metric_id (requerido), output_mode (aggregate|grouped), filtros allowlisted.
     *
     * @no_intent_catalog
     * @action_name Consulta informativa staff
     * @entity DataAccess
     * @tags staff, metrics, info
     */
    public function actionIndex(): array
    {
        $params = array_merge(Yii::$app->request->get(), Yii::$app->request->post());

        ApiDomainOperationBridge::assertOrForbidden('DataAccess.info', $params, $params);

        try {
            return (new DataAccessUiService())->renderInfo($params);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}
