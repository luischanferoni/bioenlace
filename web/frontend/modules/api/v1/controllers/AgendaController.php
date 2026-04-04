<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use common\components\Services\Agenda\AgendaRrhhCrudService;
use common\models\Agenda_rrhh;

/**
 * Agenda profesional (día operativo) y CRUD de agendas laborales por servicio ({@see Agenda_rrhh}).
 *
 * **Modelo:** 1 efector → 1 RRHH → N servicios (`rrhh_servicio`) → **1 agenda por servicio** (`id_rrhh_servicio_asignado`).
 *
 * **RBAC en dos ámbitos** (como turnos paciente vs operativo):
 * - **como-profesional:** solo el RRHH del usuario en sesión (`getIdRecursoHumano()`); un médico no lista ni edita agendas de otros.
 * - **para-efector:** staff del establecimiento; puede gestionar agendas de cualquier RRHH del efector (`id_rr_hh` en alta y filtros de listado).
 *
 * Rutas HTTP v1 y permisos `/api/agenda/...` (sin `v1` en webvimark):
 * - GET /api/v1/agenda/dia → /api/agenda/dia
 * - GET/POST …/agenda/listar-como-profesional, …/crear-como-profesional, GET/PUT/PATCH/DELETE …/ver|actualizar|eliminar-como-profesional/{id}
 * - GET/POST …/agenda/listar-para-efector, …/crear-para-efector, GET/PUT/PATCH/DELETE …/ver|actualizar|eliminar-para-efector/{id}
 */
class AgendaController extends BaseController
{
    /**
     * Citas del día (vista operativa del profesional). RBAC: /api/agenda/dia
     *
     * @action_name Ver mi agenda del día (profesional)
     * @entity Agendas
     * @tags agenda,profesional,operativo,dia,citas,turnos-asignados
     * @keywords agenda del día, turnos de hoy, pacientes hoy, mi día
     * @synonyms qué tengo hoy, citas del día, ocupación del día
     */
    public function actionDia()
    {
        return TurnosController::agendaDiaResponse();
    }

    /**
     * Listado paginado: solo agendas del RRHH del usuario. RBAC: /api/agenda/listar-como-profesional
     *
     * @action_name Listar mis agendas por servicio
     * @entity Agendas
     * @tags agenda,profesional,mis-agendas,servicio,listar
     * @keywords mis agendas laborales, horarios mis servicios, mis especialidades agenda
     */
    public function actionListarComoProfesional()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $idRrhh = $this->requireRecursoHumanoId();
        $dp = AgendaRrhhCrudService::searchForProfesional(Yii::$app->request->queryParams, $idRrhh);

        return $this->paginatedListResponse($dp);
    }

    /**
     * Detalle si la agenda es del RRHH del usuario. RBAC: /api/agenda/ver-como-profesional
     *
     * @action_name Ver detalle de mi agenda (por servicio)
     * @entity Agendas
     * @tags agenda,profesional,ver,detalle,servicio
     * @param int $id id_agenda_rrhh
     */
    public function actionVerComoProfesional($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $idEfector = $this->requireEfectorId();
        $idRrhh = $this->requireRecursoHumanoId();
        $model = AgendaRrhhCrudService::findOwnedByProfesional((int) $id, $idEfector, $idRrhh);
        if ($model === null) {
            throw new NotFoundHttpException('Agenda no encontrada.');
        }

        return [
            'success' => true,
            'data' => AgendaRrhhCrudService::toApiArray($model),
        ];
    }

    /**
     * Alta de agenda para el propio RRHH (cuerpo sin poder fijar otro id_rr_hh). RBAC: /api/agenda/crear-como-profesional
     *
     * @action_name Crear agenda para uno de mis servicios
     * @entity Agendas
     * @tags agenda,profesional,crear,servicio,horarios
     */
    public function actionCrearComoProfesional()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->createAgendaResponse(false);
    }

    /**
     * Actualizar agenda propia. RBAC: /api/agenda/actualizar-como-profesional
     *
     * @action_name Actualizar mi agenda (servicio)
     * @entity Agendas
     * @tags agenda,profesional,actualizar,editar
     * @param int $id id_agenda_rrhh
     */
    public function actionActualizarComoProfesional($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->updateAgendaResponse((int) $id, false);
    }

    /**
     * Baja lógica de agenda propia. RBAC: /api/agenda/eliminar-como-profesional
     *
     * @action_name Eliminar mi agenda (servicio)
     * @entity Agendas
     * @tags agenda,profesional,eliminar,baja
     * @param int $id id_agenda_rrhh
     */
    public function actionEliminarComoProfesional($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->deleteAgendaResponse((int) $id, false);
    }

    /**
     * Listado paginado en el efector (todos los RRHH). RBAC: /api/agenda/listar-para-efector
     *
     * @action_name Listar agendas del efector (todos los profesionales)
     * @entity Agendas
     * @tags agenda,efector,staff,listar,rrhh
     * @keywords agendas del establecimiento, listar médicos horarios
     */
    public function actionListarParaEfector()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $dp = AgendaRrhhCrudService::search(Yii::$app->request->queryParams);

        return $this->paginatedListResponse($dp);
    }

    /**
     * Detalle en el ámbito efector. RBAC: /api/agenda/ver-para-efector
     *
     * @action_name Ver agenda (gestión efector)
     * @entity Agendas
     * @tags agenda,efector,staff,ver
     * @param int $id id_agenda_rrhh
     */
    public function actionVerParaEfector($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $idEfector = $this->requireEfectorId();
        $model = AgendaRrhhCrudService::findOwnedByEfector((int) $id, $idEfector);
        if ($model === null) {
            throw new NotFoundHttpException('Agenda no encontrada.');
        }

        return [
            'success' => true,
            'data' => AgendaRrhhCrudService::toApiArray($model),
        ];
    }

    /**
     * Alta en efector; requiere id_rr_hh en JSON o query (id_rr_hh / id). RBAC: /api/agenda/crear-para-efector
     *
     * @action_name Crear agenda para un profesional del efector
     * @entity Agendas
     * @tags agenda,efector,staff,crear
     */
    public function actionCrearParaEfector()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->createAgendaResponse(true);
    }

    /**
     * RBAC: /api/agenda/actualizar-para-efector
     *
     * @action_name Actualizar agenda (gestión efector)
     * @entity Agendas
     * @param int $id id_agenda_rrhh
     */
    public function actionActualizarParaEfector($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->updateAgendaResponse((int) $id, true);
    }

    /**
     * RBAC: /api/agenda/eliminar-para-efector
     *
     * @action_name Eliminar agenda (gestión efector)
     * @entity Agendas
     * @param int $id id_agenda_rrhh
     */
    public function actionEliminarParaEfector($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->deleteAgendaResponse((int) $id, true);
    }

    private function requireEfectorId(): int
    {
        $id = (int) Yii::$app->user->getIdEfector();
        if ($id <= 0) {
            throw new BadRequestHttpException('No se pudo determinar el efector en sesión.');
        }

        return $id;
    }

    private function requireRecursoHumanoId(): int
    {
        $id = (int) Yii::$app->user->getIdRecursoHumano();
        if ($id <= 0) {
            throw new BadRequestHttpException('No se pudo determinar el recurso humano del usuario.');
        }

        return $id;
    }

    private function paginatedListResponse(ActiveDataProvider $dp): array
    {
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
     * @return array<string, mixed>
     */
    private function normalizeAgendaRequestBody(): array
    {
        $body = Yii::$app->request->getBodyParams();

        return AgendaRrhhCrudService::normalizeDayFieldsForLoad(is_array($body) ? $body : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function createAgendaResponse(bool $paraEfector): array
    {
        $idEfector = $this->requireEfectorId();
        $body = $this->normalizeAgendaRequestBody();

        if ($paraEfector) {
            $legacyRrhh = Yii::$app->request->get('id_rr_hh') ?: Yii::$app->request->get('id');
            if ($legacyRrhh !== null && $legacyRrhh !== '' && empty($body['id_rr_hh'])) {
                $body['id_rr_hh'] = (int) $legacyRrhh;
            }
            $idRrhh = (int) ($body['id_rr_hh'] ?? 0);
            unset($body['id_rr_hh']);
            if ($idRrhh <= 0) {
                throw new BadRequestHttpException('id_rr_hh es requerido para crear agenda en el efector.');
            }
        } else {
            $idRrhh = $this->requireRecursoHumanoId();
            unset($body['id_rr_hh']);
        }

        unset($body['id_agenda_rrhh'], $body['id_efector']);

        $model = new Agenda_rrhh();
        $model->id_efector = $idEfector;
        $model->id_rr_hh = $idRrhh;
        $model->load($body, '');

        AgendaRrhhCrudService::assertServicioAsignadoParaRrhhEfector(
            self::nullablePositiveInt($model->id_rrhh_servicio_asignado),
            $idRrhh,
            $idEfector
        );

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
     * @return array<string, mixed>
     */
    private function updateAgendaResponse(int $idAgenda, bool $paraEfector): array
    {
        $idEfector = $this->requireEfectorId();
        if ($paraEfector) {
            $model = AgendaRrhhCrudService::findOwnedByEfector($idAgenda, $idEfector);
        } else {
            $model = AgendaRrhhCrudService::findOwnedByProfesional($idAgenda, $idEfector, $this->requireRecursoHumanoId());
        }
        if ($model === null) {
            throw new NotFoundHttpException('Agenda no encontrada.');
        }

        $body = $this->normalizeAgendaRequestBody();
        unset($body['id_agenda_rrhh'], $body['id_efector'], $body['id_rr_hh']);

        $lockedEfector = (int) $model->id_efector;
        $lockedRrhh = (int) $model->id_rr_hh;
        $model->load($body, '');
        $model->id_efector = $lockedEfector;
        $model->id_rr_hh = $lockedRrhh;

        AgendaRrhhCrudService::assertServicioAsignadoParaRrhhEfector(
            self::nullablePositiveInt($model->id_rrhh_servicio_asignado),
            $lockedRrhh,
            $idEfector
        );

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
     * @return array<string, mixed>
     */
    private function deleteAgendaResponse(int $idAgenda, bool $paraEfector): array
    {
        $idEfector = $this->requireEfectorId();
        if ($paraEfector) {
            $model = AgendaRrhhCrudService::findOwnedByEfector($idAgenda, $idEfector);
        } else {
            $model = AgendaRrhhCrudService::findOwnedByProfesional($idAgenda, $idEfector, $this->requireRecursoHumanoId());
        }
        if ($model === null) {
            throw new NotFoundHttpException('Agenda no encontrada.');
        }
        $model->delete();

        return [
            'success' => true,
            'message' => 'Agenda eliminada.',
        ];
    }

    private static function nullablePositiveInt($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $i = (int) $v;

        return $i > 0 ? $i : null;
    }
}
