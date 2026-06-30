<?php

namespace frontend\modules\api\v1\controllers;

use common\components\Platform\Ui\Home\Service\HomePanelService;
use Yii;

/**
 * Panel de inicio unificado (web site/index + app Personal de Salud).
 *
 * GET /api/v1/home/panel
 * Query: fecha=YYYY-MM-DD, sections=id1,id2 (refresco parcial), prueba=1 (AMB debug)
 */
class HomeController extends BaseController
{
    public function actionPanel(): array
    {
        try {
            $data = (new HomePanelService())->buildPanel([
                'fecha' => Yii::$app->request->get('fecha'),
                'sections' => Yii::$app->request->get('sections'),
                'prueba' => Yii::$app->request->get('prueba') === '1',
                'id_efector' => (int) Yii::$app->request->get('id_efector', 0) ?: null,
                'subject_persona_id' => (int) Yii::$app->request->get('subject_persona_id', 0) ?: null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Panel de inicio');
    }
}
