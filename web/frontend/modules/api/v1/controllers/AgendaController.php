<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use common\components\Services\Agenda\AgendaRrhhCrudService;
use common\models\Agenda_rrhh;

/**
 * Agenda profesional y CRUD de agendas laborales (RRHH).
 *
 * - GET /api/v1/agenda/dia — turnos del día (profesional).
 * - GET /api/v1/agenda/rrhh — listar agendas del efector (filtros como {@see \common\models\busquedas\Agenda_rrhhBusqueda}).
 * - GET /api/v1/agenda/rrhh/{id} — ver una agenda.
 * - POST /api/v1/agenda/rrhh — crear (body JSON; opcional query id o id_rr_hh como en el alta web).
 * - PUT|PATCH /api/v1/agenda/rrhh/{id} — actualizar.
 * - DELETE /api/v1/agenda/rrhh/{id} — baja lógica ({@see Agenda_rrhh::delete}).
 *
 * Permisos: /api/agenda/dia, /api/agenda/listar-rrhh, /api/agenda/ver-rrhh, /api/agenda/crear-rrhh,
 * /api/agenda/actualizar-rrhh, /api/agenda/eliminar-rrhh.
 */
class AgendaController extends BaseController
{
    /**
     * Turnos del día para la agenda del profesional. RBAC: ruta /api/agenda/dia
     */
    public function actionDia()
    {
        return TurnosController::agendaDiaResponse();
    }

    /**
     * Listado paginado de agendas del efector de sesión.
     *
     * @return array{success: bool, items: array, total_count: int, page: int, per_page: int}
     */
    public function actionListarRrhh()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $dp = AgendaRrhhCrudService::search(Yii::$app->request->queryParams);
        $items = [];
        foreach ($dp->getModels() as $model) {
            $items[] = AgendaRrhhCrudService::toApiArray($model);
        }

        return [
            'success' => true,
            'items' => $items,
            'total_count' => (int) $dp->getTotalCount(),
            'page' => (int) $dp->pagination->page + 1,
            'per_page' => (int) $dp->pagination->pageSize,
        ];
    }

    /**
     * @param int $id id_agenda_rrhh
     * @return array{success: bool, data: array}
     * @throws NotFoundHttpException
     */
    public function actionVerRrhh($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $model = AgendaRrhhCrudService::findOwned((int) $id, $idEfector);
        if ($model === null) {
            throw new NotFoundHttpException('Agenda no encontrada.');
        }

        return [
            'success' => true,
            'data' => AgendaRrhhCrudService::toApiArray($model),
        ];
    }

    /**
     * @return array
     * @throws BadRequestHttpException
     */
    public function actionCrearRrhh()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('No se pudo determinar el efector en sesión.');
        }

        $body = Yii::$app->request->getBodyParams();
        $body = AgendaRrhhCrudService::normalizeDayFieldsForLoad(is_array($body) ? $body : []);
        $legacyRrhh = Yii::$app->request->get('id_rr_hh') ?: Yii::$app->request->get('id');
        if ($legacyRrhh !== null && $legacyRrhh !== '' && empty($body['id_rr_hh'])) {
            $body['id_rr_hh'] = (int) $legacyRrhh;
        }

        unset($body['id_agenda_rrhh'], $body['id_efector']);

        $model = new Agenda_rrhh();
        $model->id_efector = $idEfector;
        $model->load($body, '');

        if (!$model->validate()) {
            return $this->error('Validación fallida.', $model->errors, 422);
        }
        if (!$model->save()) {
            return $this->error('No se pudo guardar.', $model->errors, 422);
        }

        Yii::$app->response->statusCode = 201;

        return [
            'success' => true,
            'message' => 'Agenda creada.',
            'data' => AgendaRrhhCrudService::toApiArray($model),
        ];
    }

    /**
     * @param int $id id_agenda_rrhh
     * @return array{success: bool, message: string, data: array}
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     */
    public function actionActualizarRrhh($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $model = AgendaRrhhCrudService::findOwned((int) $id, $idEfector);
        if ($model === null) {
            throw new NotFoundHttpException('Agenda no encontrada.');
        }

        $body = Yii::$app->request->getBodyParams();
        $body = AgendaRrhhCrudService::normalizeDayFieldsForLoad(is_array($body) ? $body : []);
        unset($body['id_agenda_rrhh'], $body['id_efector']);

        $lockedEfector = (int) $model->id_efector;
        $model->load($body, '');
        $model->id_efector = $lockedEfector;

        if (!$model->validate()) {
            return $this->error('Validación fallida.', $model->errors, 422);
        }
        if (!$model->save()) {
            return $this->error('No se pudo guardar.', $model->errors, 422);
        }

        return [
            'success' => true,
            'message' => 'Agenda actualizada.',
            'data' => AgendaRrhhCrudService::toApiArray($model),
        ];
    }

    /**
     * @param int $id id_agenda_rrhh
     * @return array{success: bool, message: string}
     * @throws NotFoundHttpException
     */
    public function actionEliminarRrhh($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $model = AgendaRrhhCrudService::findOwned((int) $id, $idEfector);
        if ($model === null) {
            throw new NotFoundHttpException('Agenda no encontrada.');
        }
        $model->delete();

        return [
            'success' => true,
            'message' => 'Agenda eliminada.',
        ];
    }
}
