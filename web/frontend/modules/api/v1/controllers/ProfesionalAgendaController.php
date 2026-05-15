<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use common\components\Services\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaApiService;
use common\components\Services\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaUiService;
use common\components\UiScreenService;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;

/**
 * Agenda profesional (día operativo) y CRUD de agendas laborales por servicio ({@see ProfesionalEfectorServicioAgenda}).
 *
 * **Modelo:** 1 efector → PES (`profesional_efector_servicio`) → **1 agenda** por asignación.
 *
 * **Listado siempre:** el detalle por ítem viene en cada fila del listado; no hay acciones `ver-*`.
 *
 * **RBAC (alineado a Turnos: ámbito propio vs operativo sobre tercero):**
 * - **`listar`, `crear`, `actualizar`, `eliminar`:** profesional en sesión (contexto PES); efector desde sesión (no se aceptan `id_efector` ni IDs de otro profesional en query para ampliar alcance).
 * - **`listar-para-recurso`, `crear-para-recurso`, `actualizar-para-recurso`, `eliminar-para-recurso`:** staff; **`id_efector`** + **`id_profesional_efector_servicio`**.
 *
 * Permisos `/api/profesional-agenda/...` (sin `v1` en webvimark):
 * dia, listar, crear, actualizar, eliminar, listar-para-recurso, crear-para-recurso, actualizar-para-recurso, eliminar-para-recurso,
 * crear-agenda-flow, editar-agenda-flow
 */
class ProfesionalAgendaController extends BaseController
{
    /**
     * Cierre declarativo del flujo asistente «alta profesional y agenda» (solo POST; sin descriptor UI).
     * Permiso RBAC: `/api/profesional-agenda/crear-agenda-flow` (alineado al YAML `agenda.crear-profesional-flow`).
     *
     * POST /api/v1/profesional-agenda/crear-agenda-flow
     *
     * @action_name Cerrar flujo alta profesional/agenda (asistente)
     * @entity Agendas
     * @tags agenda, asistente, flow
     */
    public function actionCrearAgendaFlow(): array
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            throw new MethodNotAllowedHttpException(['POST'], 'Este endpoint solo acepta POST (cierre del flujo del asistente).');
        }
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('Se requiere efector en sesión.');
        }

        $post = $req->post();
        $idPes = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        if ($idPes > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pes === null || (int) $pes->id_efector !== $idEfector) {
                throw new ForbiddenHttpException('Asignación inválida para este efector.');
            }
        }

        return [
            'success' => true,
            'kind' => 'ui_submit_result',
            'action_id' => 'profesional-agenda.crear-agenda-flow',
            'data' => [
                'success' => true,
                'message' => 'Flujo de alta completado.',
            ],
            'errors' => null,
        ];
    }

    /**
     * Cierre declarativo del flujo asistente «editar agenda» (solo POST; sin descriptor UI).
     * Permiso RBAC: `/api/profesional-agenda/editar-agenda-flow` (alineado al YAML `agenda.editar-agenda-flow`).
     *
     * POST /api/v1/profesional-agenda/editar-agenda-flow
     *
     * @action_name Cerrar flujo editar agenda (asistente)
     * @entity Agendas
     * @tags agenda, asistente, flow
     */
    public function actionEditarAgendaFlow(): array
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            throw new MethodNotAllowedHttpException(['POST'], 'Este endpoint solo acepta POST (cierre del flujo del asistente).');
        }
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('Se requiere efector en sesión.');
        }

        $post = $req->post();
        $idPes = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        $idServicio = (int) ($post['id_servicio'] ?? 0);
        if ($idPes <= 0 || $idServicio <= 0) {
            throw new BadRequestHttpException('Indique id_profesional_efector_servicio e id_servicio.');
        }
        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
        if ($pes === null || (int) $pes->id_efector !== $idEfector) {
            throw new ForbiddenHttpException('Asignación inválida para este efector.');
        }
        if ((int) $pes->id_servicio !== $idServicio) {
            throw new BadRequestHttpException('id_servicio no coincide con la asignación profesional.');
        }

        return [
            'success' => true,
            'kind' => 'ui_submit_result',
            'action_id' => 'profesional-agenda.editar-agenda-flow',
            'data' => [
                'success' => true,
                'message' => 'Flujo de edición de agenda cerrado.',
            ],
            'errors' => null,
        ];
    }

    /**
     * UI JSON: configurar agenda semanal por servicio (cupo, forma de atención y horarios).
     *
     * GET|POST /api/v1/profesional-agenda/configurar-agenda
     *
     * @action_name Configurar agenda (horarios/cupo/modo) por servicio
     * @entity Agendas
     * @tags agenda, profesional, servicios, horarios, cupo, staff
     * @keywords configurar agenda profesional, horarios por servicio, cupo pacientes, forma de atención
     * @spa_presentation fullscreen
     */
    public function actionConfigurarAgenda(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();

        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        $defaults = ProfesionalEfectorServicioAgendaUiService::buildFieldValuesForGet($idEfector, $fromClient);
        $paramsForRender = array_merge($defaults, $fromClient);

        return UiScreenService::handleScreen(
            'profesional-agenda',
            'configurar-agenda',
            $paramsForRender,
            $req->post(),
            function (array $post) use ($idEfector): array {
                return ProfesionalEfectorServicioAgendaUiService::submitAgendaConfig($idEfector, $post);
            }
        );
    }

    /**
     * Citas del día (vista operativa). RBAC: /api/profesional-agenda/dia
     *
     * @action_name Ver mi agenda del día (profesional)
     * @entity Agendas
     * @tags agenda,profesional,operativo,dia,citas,turnos-asignados
     * @keywords agenda, agenda del día, turnos de hoy, pacientes hoy, mi día, ocupación
     * @synonyms qué tengo hoy, citas del día, ocupación del día
     */
    public function actionDia()
    {
        return TurnosController::agendaDiaResponse();
    }

    /**
     * Listado paginado del profesional en sesión (contexto PES) en el efector actual. RBAC: /api/profesional-agenda/listar
     *
     * @action_name Listar mis agendas por servicio
     * @entity Agendas
     * @tags agenda,profesional,mis-agendas,servicio,listar
     * @keywords mis agendas laborales, horarios mis servicios, mis especialidades agenda
     */
    public function actionListar()
    {
        $staffContextId = $this->requireStaffContextId();
        $params = Yii::$app->request->queryParams;
        unset($params['id_profesional_contexto'], $params['id_efector']);

        $dp = ProfesionalEfectorServicioAgendaApiService::searchForStaffContext($params, $staffContextId);

        return $this->paginatedListResponse($dp);
    }

    /**
     * Listado de un médico concreto. Query obligatorio: `id_efector` y `id_profesional_efector_servicio`. RBAC: /api/profesional-agenda/listar-para-recurso
     *
     * @action_name Listar agendas de un recurso en un efector
     * @entity Agendas
     * @tags agenda, staff, listar, efector
     * @keywords agendas de un médico, listar horarios profesional
     */
    public function actionListarParaRecurso()
    {
        $params = Yii::$app->request->queryParams;
        $idEfector = (int) ($params['id_efector'] ?? 0);
        $idStaff = ProfesionalEfectorServicio::staffContextIdFromRequestParams($params);
        if ($idEfector <= 0 || $idStaff <= 0) {
            throw new BadRequestHttpException(
                'id_efector e id_profesional_efector_servicio son obligatorios.'
            );
        }
        $this->assertEfectorParamMatchesSessionWhenPresent($idEfector);
        ProfesionalEfectorServicioAgendaApiService::assertProfesionalEfectorServicioEnEfector($idStaff, $idEfector);
        unset($params['id_profesional_contexto']);
        $params['id_profesional_efector_servicio'] = $idStaff;
        $params['id_efector'] = $idEfector;
        $dp = ProfesionalEfectorServicioAgendaApiService::search($params);

        return $this->paginatedListResponse($dp);
    }

    /**
     * Alta para el profesional en contexto. RBAC: /api/profesional-agenda/crear
     *
     * @action_name Crear agenda para uno de mis servicios
     * @entity Agendas
     * @tags agenda,profesional,crear,servicio,horarios
     */
    public function actionCrear()
    {
        return $this->createAgendaResponse(false);
    }

    /**
     * Alta para otro profesional: `id_efector` + `id_profesional_efector_servicio` (body o query). RBAC: /api/profesional-agenda/crear-para-recurso
     *
     * @action_name Crear agenda para un profesional (staff)
     * @entity Agendas
     * @tags agenda,staff,crear
     */
    public function actionCrearParaRecurso()
    {
        return $this->createAgendaResponse(true);
    }

    /**
     * RBAC: /api/profesional-agenda/actualizar
     *
     * @action_name Actualizar mi agenda (servicio)
     * @entity Agendas
     * @tags agenda,profesional,actualizar,editar
     * @param int $id id `profesional_efector_servicio_agenda`
     */
    public function actionActualizar($id)
    {
        return $this->updateAgendaResponse((int) $id, false);
    }

    /**
     * RBAC: /api/profesional-agenda/actualizar-para-recurso
     *
     * @action_name Actualizar agenda de un profesional (staff)
     * @entity Agendas
     * @tags agenda,staff,actualizar,editar
     * @keywords actualizar agenda profesional, editar horarios por servicio
     * @param int $id id `profesional_efector_servicio_agenda`
     */
    public function actionActualizarParaRecurso($id)
    {
        return $this->updateAgendaResponse((int) $id, true);
    }

    /**
     * RBAC: /api/profesional-agenda/eliminar
     *
     * @action_name Eliminar mi agenda (servicio)
     * @entity Agendas
     * @tags agenda,profesional,eliminar,baja
     * @param int $id id `profesional_efector_servicio_agenda`
     */
    public function actionEliminar($id)
    {
        return $this->deleteAgendaResponse((int) $id, false);
    }

    /**
     * RBAC: /api/profesional-agenda/eliminar-para-recurso
     *
     * @action_name Eliminar agenda de un profesional (staff)
     * @entity Agendas
     * @tags agenda,staff,eliminar,baja
     * @keywords eliminar agenda profesional, baja agenda por servicio
     * @param int $id id `profesional_efector_servicio_agenda`
     */
    public function actionEliminarParaRecurso($id)
    {
        return $this->deleteAgendaResponse((int) $id, true);
    }

    /**
     * Si el usuario tiene efector en sesión, el parámetro debe coincidir (staff no cruza establecimientos).
     */
    private function assertEfectorParamMatchesSessionWhenPresent(int $idEfectorParam): void
    {
        $sessionEfector = (int) Yii::$app->user->getIdEfector();
        if ($sessionEfector > 0 && $sessionEfector !== $idEfectorParam) {
            throw new ForbiddenHttpException('El efector indicado no coincide con su sesión.');
        }
    }

    private function requireEfectorId(): int
    {
        $id = (int) Yii::$app->user->getIdEfector();
        if ($id <= 0) {
            throw new BadRequestHttpException('No se pudo determinar el efector en sesión.');
        }

        return $id;
    }

    private function requireStaffContextId(): int
    {
        $id = (int) Yii::$app->user->getIdProfesionalEfectorServicio();
        if ($id <= 0) {
            throw new BadRequestHttpException('No se pudo determinar el contexto profesional en sesión.');
        }

        return $id;
    }

    private function paginatedListResponse(ActiveDataProvider $dp): array
    {
        $items = [];
        foreach ($dp->getModels() as $model) {
            $items[] = ProfesionalEfectorServicioAgendaApiService::toApiArray($model);
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

        return ProfesionalEfectorServicioAgendaApiService::normalizeDayFieldsForLoad(is_array($body) ? $body : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function createAgendaResponse(bool $paraRecurso): array
    {
        $body = $this->normalizeAgendaRequestBody();

        if ($paraRecurso) {
            $merged = array_merge(Yii::$app->request->get(), is_array($body) ? $body : []);
            $idEfector = (int) ($merged['id_efector'] ?? 0);
            $idPes = ProfesionalEfectorServicio::staffContextIdFromRequestParams($merged);
            unset($body['id_efector'], $body['id_profesional_contexto'], $body['id_profesional_efector_servicio']);

            if ($idEfector <= 0 || $idPes <= 0) {
                throw new BadRequestHttpException('id_efector e id_profesional_efector_servicio son requeridos para crear agenda para otro recurso.');
            }
            $this->assertEfectorParamMatchesSessionWhenPresent($idEfector);
            $pes = ProfesionalEfectorServicioAgendaApiService::assertProfesionalEfectorServicioEnEfector($idPes, $idEfector);
        } else {
            $merged = array_merge(Yii::$app->request->get(), is_array($body) ? $body : []);
            $idEfector = $this->requireEfectorId();
            $idPes = ProfesionalEfectorServicio::staffContextIdFromRequestParams($merged);
            if ($idPes <= 0) {
                $idPes = (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
            }
            unset($body['id_efector'], $body['id_profesional_contexto'], $body['id_profesional_efector_servicio']);

            if ($idPes <= 0) {
                return $this->error(
                    'id_profesional_efector_servicio es obligatorio (sesión operativa o cuerpo).',
                    ['id_profesional_efector_servicio' => ['Requerido']],
                    422
                );
            }
            $pes = ProfesionalEfectorServicioAgendaApiService::assertProfesionalEfectorServicioEnEfector($idPes, $idEfector);
        }

        if (ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio((int) $pes->id) !== null) {
            return $this->error('Ya existe una agenda para este servicio.', [], 422);
        }

        $model = new ProfesionalEfectorServicioAgenda();
        $model->load($body, '');
        $model->id_profesional_efector_servicio = (int) $pes->id;
        $model->id_efector = $idEfector;

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
            'data' => ProfesionalEfectorServicioAgendaApiService::toApiArray($model),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateAgendaResponse(int $idAgenda, bool $paraRecurso): array
    {
        $idEfector = $this->requireEfectorId();
        if ($paraRecurso) {
            $model = ProfesionalEfectorServicioAgendaApiService::findOwnedByEfector($idAgenda, $idEfector);
        } else {
            $model = ProfesionalEfectorServicioAgendaApiService::findOwnedByStaffContext($idAgenda, $idEfector, $this->requireStaffContextId());
        }
        if ($model === null) {
            throw new NotFoundHttpException('Agenda no encontrada.');
        }

        $body = $this->normalizeAgendaRequestBody();
        unset($body['id_efector'], $body['id_profesional_contexto'], $body['id_profesional_efector_servicio']);

        $lockedEfector = (int) $model->id_efector;
        $lockedPes = (int) $model->id_profesional_efector_servicio;
        $model->load($body, '');
        $model->id_efector = $lockedEfector;
        $model->id_profesional_efector_servicio = $lockedPes;

        if (!$model->validate()) {
            return $this->error('Validación fallida.', $model->errors, 422);
        }
        if (!$model->save()) {
            return $this->error('No se pudo guardar.', $model->errors, 422);
        }

        return [
            'success' => true,
            'message' => 'Agenda actualizada.',
            'data' => ProfesionalEfectorServicioAgendaApiService::toApiArray($model),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteAgendaResponse(int $idAgenda, bool $paraRecurso): array
    {
        $idEfector = $this->requireEfectorId();
        if ($paraRecurso) {
            $model = ProfesionalEfectorServicioAgendaApiService::findOwnedByEfector($idAgenda, $idEfector);
        } else {
            $model = ProfesionalEfectorServicioAgendaApiService::findOwnedByStaffContext($idAgenda, $idEfector, $this->requireStaffContextId());
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
}
