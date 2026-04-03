<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\models\RrhhEfector;
use common\models\ServiciosEfector;

/**
 * API Rrhh: autocomplete de RRHH por efector/servicio; servicios asignados al RRHH del usuario en un efector.
 * Autocomplete: migrado desde frontend\controllers\RrhhController::actionRrhhAutocomplete.
 * Servicios por RRHH: migrado desde frontend\controllers\RrhhEfectorController::actionServiciosPorRrhh.
 */
class RrhhController extends BaseController
{
    public static $authenticatorExcept = ['autocomplete'];

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    /**
     * GET/POST /api/v1/rrhh/autocomplete
     * Parámetros: id_efector, id_servicio (requeridos); q, limit, sort_by, sort_order, efector_nombre, servicio_nombre (opcionales).
     */
    public function actionAutocomplete($q = null)
    {
        $request = Yii::$app->request;
        $idEfector = $request->get('id_efector') ?: $request->post('id_efector');
        $idServicio = $request->get('id_servicio') ?: $request->post('id_servicio');
        if (empty($idEfector) || empty($idServicio)) {
            throw new BadRequestHttpException('id_efector e id_servicio son requeridos');
        }
        $out = ['results' => ['id' => '', 'text' => '']];
        $q = $q ?? $request->get('q') ?? $request->post('q');
        $filters = [
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
        ];
        if ($request->get('limit') || $request->post('limit')) {
            $filters['limit'] = $request->get('limit') ?: $request->post('limit');
        }
        if ($request->get('efector_nombre') || $request->post('efector_nombre')) {
            $filters['efector_nombre'] = $request->get('efector_nombre') ?: $request->post('efector_nombre');
        }
        if ($request->get('servicio_nombre') || $request->post('servicio_nombre')) {
            $filters['servicio_nombre'] = $request->get('servicio_nombre') ?: $request->post('servicio_nombre');
        }
        if ($request->get('sort_by') || $request->post('sort_by')) {
            $filters['sort_by'] = $request->get('sort_by') ?: $request->post('sort_by');
        }
        if ($request->get('sort_order') || $request->post('sort_order')) {
            $filters['sort_order'] = $request->get('sort_order') ?: $request->post('sort_order');
        }
        if ($q === null && count($filters) <= 2) {
            return $out;
        }
        $out['results'] = array_values(RrhhEfector::autocompleteRrhh($q, $filters));
        return $out;
    }

    /**
     * POST /api/v1/rrhh/servicios-por-rrhh
     * Parámetros: id_efector (o idEfector). Devuelve los servicios del RRHH de la persona autenticada en ese efector,
     * filtrados como en el flujo web (servicio activo en el efector o nombre «ADMINISTRAR EFECTOR»).
     *
     * @return array{servicios: list<array{id_servicio: int, nombre: string}>}
     */
    public function actionServiciosPorRrhh()
    {
        $request = Yii::$app->request;
        $idEfector = $request->post('id_efector') ?: $request->post('idEfector')
            ?: $request->get('id_efector') ?: $request->get('idEfector');
        if ($idEfector === null || $idEfector === '') {
            throw new BadRequestHttpException('id_efector es requerido');
        }
        $idEfector = (int) $idEfector;

        $rrhhEfector = RrhhEfector::find()
            ->where([
                'id_efector' => $idEfector,
                'id_persona' => Yii::$app->user->getIdPersona(),
            ])
            ->one();

        $servicios = [];
        if ($rrhhEfector !== null) {
            $rrhhServicios = $rrhhEfector->getRrhhServicio()->with('servicio')->all();
            foreach ($rrhhServicios as $rrhhServicio) {
                $servicioEfector = ServiciosEfector::findActive()
                    ->where([
                        'id_efector' => $idEfector,
                        'id_servicio' => $rrhhServicio->id_servicio,
                    ])
                    ->one();

                $nombreServicio = $rrhhServicio->servicio !== null
                    ? (string) $rrhhServicio->servicio->nombre
                    : '';

                if (
                    ($servicioEfector !== null && $servicioEfector->deleted_at === null)
                    || $nombreServicio === 'ADMINISTRAR EFECTOR'
                ) {
                    $servicios[] = [
                        'id_servicio' => (int) $rrhhServicio->id_servicio,
                        'nombre' => $nombreServicio,
                    ];
                }
            }
        }

        return ['servicios' => $servicios];
    }
}
