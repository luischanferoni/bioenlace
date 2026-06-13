<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Core\DataAccess\DataAccessUiService;
use common\components\Core\Permission\Domain\ApiDomainOperationBridge;

/**
 * Listados por métrica DataAccess.
 */
class ListarController extends BaseController
{
    /**
     * Ejecuta una métrica en modo rows y devuelve ui_json con listado.
     *
     * GET|POST /api/v1/listar
     *
     * Parámetros: metric_id (requerido), filtros allowlisted, limit.
     *
     * @no_intent_catalog
     * @action_name Listado
     * @entity DataAccess
     * @tags staff, metrics, list
     */
    public function actionIndex(): array
    {
        $params = array_merge(Yii::$app->request->get(), Yii::$app->request->post());

        ApiDomainOperationBridge::assertOrForbidden('DataAccess.list', $params, $params);

        try {
            return (new DataAccessUiService())->renderListar($params);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}
