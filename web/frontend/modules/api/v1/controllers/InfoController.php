<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Platform\Core\DataAccess\DataAccessUiService;
use common\components\Platform\Core\Permission\Domain\ApiDomainOperationBridge;

/**
 * Consultas staff agregadas / informativas (métricas DataAccess).
 *
 * Es el transporte HTTP del `open_ui` de los intents que declaran `action_id: data-access.info`,
 * no un endpoint retirado. Lo que no se hace es reabrirlo como intent NL: cada consulta concreta
 * entra por su intent con `metric_id` (p. ej. `profesionales.conteo-efector`).
 *
 * @see \common\components\Platform\Core\DataAccess\README.md
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
