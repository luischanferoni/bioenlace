<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\UiScreenService;
use common\models\Servicio;

/**
 * API Servicios: views JSON embebibles (selección/autocomplete) para flujos conversacionales.
 */
class ServiciosController extends BaseController
{
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    /**
     * View embebible: elegir servicio.
     *
     * GET|POST /api/v1/servicios/elegir
     *
     * @action_name Elegir servicio
     * @entity Servicios
     * @tags views, ui, servicio
     * @keywords elegir servicio, especialidad, seleccionar especialidad
     */
    public function actionElegir(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'servicios',
            'elegir',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $rows = Servicio::find()
                ->where(['deleted_at' => null])
                ->orderBy(['nombre' => SORT_ASC])
                ->all();
            $items = [];
            foreach ($rows as $s) {
                $items[] = [
                    'id' => (string) (int) $s->id_servicio,
                    'name' => (string) $s->nombre,
                ];
            }
            $ui = UiScreenService::withListBlockItems($ui, $items);
        }

        return $ui;
    }
}

