<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Platform\Core\DataAccess\DataAccessUiService;
use common\components\Platform\Core\Permission\Domain\ApiDomainOperationBridge;

/**
 * Listados por métrica DataAccess.
 *
 * Es el transporte HTTP del `open_ui` de los intents que declaran `action_id: data-access.listar`,
 * no un endpoint retirado. Lo que no se hace es reabrirlo como intent NL: cada listado concreto
 * entra por su intent con `metric_id` (p. ej. `profesionales.listado-efector`).
 *
 * @see \common\components\Platform\Core\DataAccess\README.md
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
