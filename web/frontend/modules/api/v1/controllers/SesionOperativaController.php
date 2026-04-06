<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Services\SesionOperativa\SesionOperativaService;

/**
 * API Sesión Operativa: establece contexto operativo en sesión.
 *
 * POST /api/v1/sesion-operativa/establecer
 * Body: { "efector_id": 123, "servicio_id": 456, "encounter_class": "AMB" }
 */
class SesionOperativaController extends BaseController
{
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    public function actionEstablecer()
    {
        try {
            $body = Yii::$app->request->bodyParams ?: [];

            /** @var SesionOperativaService $service */
            $service = Yii::$container->has(SesionOperativaService::class)
                ? Yii::$container->get(SesionOperativaService::class)
                : new SesionOperativaService();

            $data = $service->establecer($body);

            return $this->success($data, 'Sesión operativa establecida correctamente');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\RuntimeException $e) {
            // Caso típico: no hay vínculo RRHH-Efector (roles x efector sin fila en rrhh_efector)
            return $this->error($e->getMessage(), null, 404);
        } catch (\Throwable $e) {
            Yii::error('Error estableciendo sesión operativa: ' . $e->getMessage());
            return $this->error('Error al establecer sesión operativa', null, 500);
        }
    }
}

