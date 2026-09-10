<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use common\components\Platform\Core\DataAccess\DataAccessEditUiService;
use common\components\Platform\Core\Permission\Domain\ApiDomainOperationBridge;

/**
 * Edición dispersa staff (superficies / aspectos / sujeto vía listar).
 *
 * Es el transporte HTTP del `open_ui` de los intents que declaran `action_id: data-access.editar`,
 * no un endpoint retirado. Lo que no se hace es reabrirlo como intent NL: cada caso concreto entra
 * por su intent con `edit_surface_id` (agenda, identidad profesional).
 *
 * @see \common\components\Platform\Core\DataAccess\README.md
 */
class EditarController extends BaseController
{
    /**
     * Flujo de edición por superficie y aspectos autorizados.
     *
     * GET|POST /api/v1/editar
     *
     * Parámetros: step (surfaces|aspects|subjects|form|confirm|apply), surface_id, aspect_ids,
     * sujeto (id_profesional_efector_servicio / id_persona+id_servicio), filtros en step=subjects.
     *
     * @no_intent_catalog
     * @action_name Edición dispersa staff
     * @entity DataAccess
     * @tags staff, metrics, edit
     */
    public function actionIndex(): array
    {
        $params = array_merge(Yii::$app->request->get(), Yii::$app->request->post());

        ApiDomainOperationBridge::assertOrForbidden('DataAccess.edit', $params, $params);

        try {
            return (new DataAccessEditUiService())->render($params);
        } catch (ForbiddenHttpException $e) {
            Yii::$app->response->statusCode = 403;
            throw $e;
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}
