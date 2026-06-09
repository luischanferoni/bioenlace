<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Clinical\CareCohort\Service\CarePackAssistanceService;
use common\components\Clinical\CareCohort\Service\CarePackFollowupService;

/**
 * Packs de cohorte expuestos al paciente (asistencia pre-consulta, seguimiento futuro).
 */
class CarePacksController extends BaseController
{
    /**
     * Asistencia pre-consulta: preguntas dinámicas desde pack de cohorte.
     *
     * GET /api/v1/care-packs/assistance?encounter_id= | turno_id=
     * POST mismo path — guarda respuestas (ui_submit_result).
     *
     * @action_name Asistencia pre-consulta (pack cohorte)
     * @entity CarePack
     * @tags clinical, paciente, ui
     */
    public function actionAssistance(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $service = new CarePackAssistanceService();

        try {
            if ($req->isPost) {
                return $service->submitResponses($params);
            }

            return $service->renderAssistance($params);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * Seguimiento post-consulta: educación + formulario de evolución por touchpoint.
     *
     * GET /api/v1/care-packs/followup?touchpoint_id= | encounter_id= + touchpoint_key=
     * POST mismo path — guarda respuestas (ui_submit_result).
     *
     * @action_name Seguimiento post-consulta (pack cohorte)
     * @entity CarePack
     * @tags clinical, paciente, ui
     */
    public function actionFollowup(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $service = new CarePackFollowupService();

        try {
            if ($req->isPost) {
                return $service->submitResponses($params);
            }

            return $service->renderFollowup($params);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}
