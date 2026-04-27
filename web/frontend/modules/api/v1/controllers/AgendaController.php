<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use common\components\Services\Agenda\AgendaRrhhService;
use common\components\Services\Rrhh\RrhhAgendaUiService;
use common\components\UiScreenService;
use common\models\Agenda_rrhh;

/**
 * Agenda profesional (día operativo) y CRUD de agendas laborales por servicio ({@see Agenda_rrhh}).
 *
 * **Modelo:** 1 efector → 1 RRHH → N servicios (`rrhh_servicio`) → **1 agenda por servicio** (`id_rrhh_servicio_asignado`).
 *
 * **Listado siempre:** el detalle por ítem viene en cada fila del listado; no hay acciones `ver-*`.
 *
 * **RBAC (alineado a Turnos: ámbito propio vs operativo sobre tercero):**
 * - **`listar`, `crear`, `actualizar`, `eliminar`:** RRHH del usuario en sesión; efector desde sesión (no se aceptan `id_rr_hh` / `id_efector` en query para ampliar alcance).
 * - **`listar-para-recurso`, `crear-para-recurso`, `actualizar-para-recurso`, `eliminar-para-recurso`:** staff; listar exige **siempre** `id_efector` e `id_rr_hh` en query; alta exige ambos en cuerpo o query.
 *
 * Permisos `/api/agenda/...` (sin `v1` en webvimark):
 * dia, listar, crear, actualizar, eliminar, listar-para-recurso, crear-para-recurso, actualizar-para-recurso, eliminar-para-recurso
 */
class AgendaController extends BaseController
{
    /**
     * UI JSON: wizard agenda (RRHH → servicio → agenda semanal → condición laboral).
     *
     * GET|POST /api/v1/agenda/editar-agenda
     *
     * @action_name Editar agenda y condición laboral (agenda)
     * @entity Agendas
     * @tags agenda, rrhh, servicios, condiciones laborales, staff
     * @keywords editar agenda profesional, horarios por servicio, condición laboral
     * @spa_presentation fullscreen
     */
    public function actionEditarAgenda(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();

        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        $defaults = RrhhAgendaUiService::buildFieldValuesForGet($idEfector, $fromClient);
        $paramsForRender = array_merge($defaults, $fromClient);

        return UiScreenService::handleScreen(
            'agenda',
            'editar-agenda',
            $paramsForRender,
            $req->post(),
            function (array $post) use ($idEfector): array {
                return RrhhAgendaUiService::submit($idEfector, $post);
            }
        );
    }

    /**
     * Citas del día (vista operativa). RBAC: /api/agenda/dia
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
     * Listado paginado del RRHH logueado en el efector de sesión. RBAC: /api/agenda/listar
     *
     * @action_name Listar mis agendas por servicio
     * @entity Agendas
     * @tags agenda,profesional,mis-agendas,servicio,listar
     * @keywords mis agendas laborales, horarios mis servicios, mis especialidades agenda
     */
    public function actionListar()
    {
        $idRrhh = $this->requireRecursoHumanoId();
        $params = Yii::$app->request->queryParams;
        unset($params['id_rr_hh'], $params['id_efector']);

        $dp = AgendaRrhhService::searchForProfesional($params, $idRrhh);

        return $this->paginatedListResponse($dp);
    }

    /**
     * Listado de un médico concreto. Query obligatorio: id_efector, id_rr_hh. RBAC: /api/agenda/listar-para-recurso
     *
     * @action_name Listar agendas de un recurso en un efector
     * @entity Agendas
     * @tags agenda,staff,listar,rrhh,efector
     * @keywords agendas de un médico, listar horarios profesional
     */
    public function actionListarParaRecurso()
    {
        $params = Yii::$app->request->queryParams;
        $idEfector = (int) ($params['id_efector'] ?? 0);
        $idRrhh = (int) ($params['id_rr_hh'] ?? 0);
        if ($idEfector <= 0 || $idRrhh <= 0) {
            throw new BadRequestHttpException('id_efector e id_rr_hh son obligatorios.');
        }
        $this->assertEfectorParamMatchesSessionWhenPresent($idEfector);
        AgendaRrhhService::assertRrhhPerteneceAEfector($idRrhh, $idEfector);

        $dp = AgendaRrhhService::searchParaRecurso($params, $idEfector, $idRrhh);

        return $this->paginatedListResponse($dp);
    }

    /**
     * Alta para el propio RRHH. RBAC: /api/agenda/crear
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
     * Alta para otro RRHH (id_efector + id_rr_hh en body o query). RBAC: /api/agenda/crear-para-recurso
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
     * RBAC: /api/agenda/actualizar
     *
     * @action_name Actualizar mi agenda (servicio)
     * @entity Agendas
     * @tags agenda,profesional,actualizar,editar
     * @param int $id id_agenda_rrhh
     */
    public function actionActualizar($id)
    {
        return $this->updateAgendaResponse((int) $id, false);
    }

    /**
     * RBAC: /api/agenda/actualizar-para-recurso
     *
     * @action_name Actualizar agenda de un profesional (staff)
     * @entity Agendas
     * @tags agenda,staff,actualizar,editar
     * @keywords actualizar agenda profesional, editar horarios por servicio
     * @param int $id id_agenda_rrhh
     */
    public function actionActualizarParaRecurso($id)
    {
        return $this->updateAgendaResponse((int) $id, true);
    }

    /**
     * RBAC: /api/agenda/eliminar
     *
     * @action_name Eliminar mi agenda (servicio)
     * @entity Agendas
     * @tags agenda,profesional,eliminar,baja
     * @param int $id id_agenda_rrhh
     */
    public function actionEliminar($id)
    {
        return $this->deleteAgendaResponse((int) $id, false);
    }

    /**
     * RBAC: /api/agenda/eliminar-para-recurso
     *
     * @action_name Eliminar agenda de un profesional (staff)
     * @entity Agendas
     * @tags agenda,staff,eliminar,baja
     * @keywords eliminar agenda profesional, baja agenda por servicio
     * @param int $id id_agenda_rrhh
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
            $items[] = AgendaRrhhService::toApiArray($model);
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

        return AgendaRrhhService::normalizeDayFieldsForLoad(is_array($body) ? $body : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function createAgendaResponse(bool $paraRecurso): array
    {
        $body = $this->normalizeAgendaRequestBody();

        if ($paraRecurso) {
            $idEfector = (int) ($body['id_efector'] ?? Yii::$app->request->get('id_efector') ?? 0);
            $idRrhh = (int) ($body['id_rr_hh'] ?? 0);
            if ($idRrhh <= 0) {
                $legacy = Yii::$app->request->get('id_rr_hh') ?: Yii::$app->request->get('id');
                if ($legacy !== null && $legacy !== '') {
                    $idRrhh = (int) $legacy;
                }
            }
            unset($body['id_efector'], $body['id_rr_hh']);
            if ($idEfector <= 0 || $idRrhh <= 0) {
                throw new BadRequestHttpException('id_efector e id_rr_hh son requeridos para crear agenda para otro recurso.');
            }
            $this->assertEfectorParamMatchesSessionWhenPresent($idEfector);
            AgendaRrhhService::assertRrhhPerteneceAEfector($idRrhh, $idEfector);
        } else {
            $idEfector = $this->requireEfectorId();
            $idRrhh = $this->requireRecursoHumanoId();
            unset($body['id_efector'], $body['id_rr_hh']);
        }

        unset($body['id_agenda_rrhh']);

        $model = new Agenda_rrhh();
        $model->id_efector = $idEfector;
        $model->id_rr_hh = $idRrhh;
        $model->load($body, '');

        AgendaRrhhService::assertServicioAsignadoParaRrhhEfector(
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
            'data' => AgendaRrhhService::toApiArray($model),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateAgendaResponse(int $idAgenda, bool $paraRecurso): array
    {
        $idEfector = $this->requireEfectorId();
        if ($paraRecurso) {
            $model = AgendaRrhhService::findOwnedByEfector($idAgenda, $idEfector);
        } else {
            $model = AgendaRrhhService::findOwnedByProfesional($idAgenda, $idEfector, $this->requireRecursoHumanoId());
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

        AgendaRrhhService::assertServicioAsignadoParaRrhhEfector(
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
            'data' => AgendaRrhhService::toApiArray($model),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteAgendaResponse(int $idAgenda, bool $paraRecurso): array
    {
        $idEfector = $this->requireEfectorId();
        if ($paraRecurso) {
            $model = AgendaRrhhService::findOwnedByEfector($idAgenda, $idEfector);
        } else {
            $model = AgendaRrhhService::findOwnedByProfesional($idAgenda, $idEfector, $this->requireRecursoHumanoId());
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
