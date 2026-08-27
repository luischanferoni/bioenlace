<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use common\components\Domain\Organization\Service\ProfesionalHorario\ProfesionalHorarioActivaService;
use common\components\Domain\Organization\Service\ProfesionalHorario\ProfesionalHorarioService;
use common\components\Domain\Organization\Service\ProfesionalHorario\ProfesionalHorarioUiFlowService;
use common\models\ProfesionalHorario;
use common\models\ProfesionalEfectorServicio;

/**
 * Horario de presencia EMER e IMP (entrada–salida). No expone cupos a pacientes.
 *
 * **RBAC** `/api/profesional-horarios/...` (sin v1):
 * - propio: listar, crear, actualizar, eliminar, gestionar, elegir-pes, listar-activas
 * - staff: *-para-recurso (+ id_efector / id_persona o PES)
 */
class ProfesionalHorariosController extends BaseController
{
    /**
     * GET|POST /api/v1/profesional-horarios/elegir-pes
     *
     * Lista PES del profesional (propio o staff) para asociar horario a un servicio.
     *
     * @action_name Elegir asignación PES para horario
     * @entity Horarios
     * @tags horario,pes,servicio
     * @spa_presentation fullscreen
     */
    public function actionElegirPes(): array
    {
        $req = Yii::$app->request;
        $idEfector = $this->requireEfectorId();
        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        $modoStaff = ((string) ($fromClient['modo'] ?? '') === 'staff');

        $ui = \common\components\Platform\Ui\UiScreenService::handleScreen(
            'profesional-horarios',
            'elegir-pes',
            $fromClient,
            $req->isPost ? $req->post() : [],
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (($ui['kind'] ?? '') === 'ui_definition' && ($ui['ui_type'] ?? '') === 'ui_json') {
            $idPersona = 0;
            if ($modoStaff) {
                $idPesCtx = ProfesionalEfectorServicio::staffContextIdFromRequestParams($fromClient);
                if ($idPesCtx > 0) {
                    $pes = ProfesionalEfectorServicio::findOne(['id' => $idPesCtx, 'deleted_at' => null]);
                    if ($pes !== null && (int) $pes->id_efector === $idEfector) {
                        $idPersona = (int) $pes->id_persona;
                    }
                }
            } else {
                $idPersona = (int) Yii::$app->user->getIdPersona();
            }
            if ($idPersona <= 0) {
                throw new BadRequestHttpException('No se pudo resolver el profesional para listar asignaciones.');
            }

            $pesRows = ProfesionalEfectorServicio::find()
                ->where([
                    'id_persona' => $idPersona,
                    'id_efector' => $idEfector,
                    'deleted_at' => null,
                ])
                ->with('servicio')
                ->orderBy(['id_servicio' => SORT_ASC])
                ->all();
            $uiItems = [];
            foreach ($pesRows as $pes) {
                $nombre = $pes->servicio !== null
                    ? (string) $pes->servicio->nombre
                    : ('Servicio #' . $pes->id_servicio);
                $uiItems[] = [
                    'id' => (string) (int) $pes->id,
                    'name' => $nombre,
                    'meta' => [
                        'id_servicio' => (int) $pes->id_servicio,
                        'id_profesional_efector_servicio' => (int) $pes->id,
                    ],
                ];
            }
            $ui = \common\components\Platform\Ui\UiScreenService::withListBlockItems($ui, $uiItems);
            $ui['action_id'] = 'profesional-horarios.elegir-pes';
        }

        return $ui;
    }

    /**
     * GET|POST /api/v1/profesional-horarios/elegir-encounter-class
     *
     * Chips/lista AMB | EMER | IMP para el flujo «Mis horarios».
     *
     * @action_name Elegir tipo de horario (ambulatorio / guardia / internación)
     * @entity Horarios
     * @tags horario,agenda,horarios,encounter
     * @spa_presentation fullscreen
     */
    public function actionElegirEncounterClass(): array
    {
        $req = Yii::$app->request;
        $this->requireEfectorId();
        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);

        $ui = \common\components\Platform\Ui\UiScreenService::handleScreen(
            'profesional-horarios',
            'elegir-encounter-class',
            $fromClient,
            $req->isPost ? $req->post() : [],
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
        if (($ui['kind'] ?? '') === 'ui_definition') {
            $ui['action_id'] = 'profesional-horarios.elegir-encounter-class';
            if ((string) ($fromClient['solo_horario_interval'] ?? '') === '1'
                && isset($ui['blocks']) && is_array($ui['blocks'])) {
                foreach ($ui['blocks'] as &$block) {
                    if (!is_array($block) || ($block['kind'] ?? '') !== 'list') {
                        continue;
                    }
                    $items = $block['items'] ?? [];
                    if (!is_array($items)) {
                        continue;
                    }
                    $filtered = [];
                    foreach ($items as $it) {
                        if (!is_array($it)) {
                            continue;
                        }
                        $id = strtoupper(trim((string) ($it['id'] ?? '')));
                        if ($id === 'EMER' || $id === 'IMP') {
                            $filtered[] = $it;
                        }
                    }
                    $block['items'] = $filtered;
                    $block['title'] = '¿Guardia o internación?';
                }
                unset($block);
            }
        }

        return $ui;
    }

    /**
     * GET|POST /api/v1/profesional-horarios/gestionar
     *
     * @action_name Gestionar horario (guardia / internación)
     * @entity Horarios
     * @tags horario,guardia,internacion,agenda
     * @spa_presentation fullscreen
     */
    public function actionGestionar(): array
    {
        $req = Yii::$app->request;
        $idEfector = $this->requireEfectorId();
        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        $allowOwn = !((string) ($fromClient['modo'] ?? '') === 'staff');

        if ($req->isPost) {
            return ProfesionalHorarioUiFlowService::handlePost($idEfector, $fromClient, $allowOwn);
        }

        return ProfesionalHorarioUiFlowService::renderForm($idEfector, $fromClient, $allowOwn);
    }

    /**
     * GET /api/v1/profesional-horarios/listar-activas
     *
     * Query: encounter_class=EMER|IMP, opcional at=YYYY-MM-DD HH:MM:SS
     */
    public function actionListarActivas(): array
    {
        $idEfector = $this->requireEfectorId();
        $params = Yii::$app->request->get();
        $class = strtoupper(trim((string) ($params['encounter_class'] ?? 'EMER')));
        $at = isset($params['at']) ? (string) $params['at'] : null;

        return [
            'success' => true,
            'data' => ProfesionalHorarioActivaService::panelPayload($idEfector, $class, $at),
        ];
    }

    /**
     * GET /api/v1/profesional-horarios/listar
     */
    public function actionListar(): array
    {
        $idEfector = $this->requireEfectorId();
        $idPersona = (int) Yii::$app->user->getIdPersona();
        $params = array_merge(Yii::$app->request->get(), [
            'id_efector' => $idEfector,
            'id_persona' => $idPersona,
        ]);

        return $this->listResponse($params);
    }

    /**
     * GET /api/v1/profesional-horarios/listar-para-recurso
     */
    public function actionListarParaRecurso(): array
    {
        $params = Yii::$app->request->get();
        $idEfector = (int) ($params['id_efector'] ?? 0);
        if ($idEfector <= 0) {
            $idEfector = $this->requireEfectorId();
        }
        $this->assertEfectorParamMatchesSessionWhenPresent($idEfector);
        $params['id_efector'] = $idEfector;

        $idPes = ProfesionalEfectorServicio::staffContextIdFromRequestParams($params);
        if ($idPes > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pes === null || (int) $pes->id_efector !== $idEfector) {
                throw new BadRequestHttpException('PES inválido.');
            }
            $params['id_persona'] = (int) $pes->id_persona;
        }

        return $this->listResponse($params);
    }

    /**
     * POST /api/v1/profesional-horarios/crear
     */
    public function actionCrear(): array
    {
        return $this->createResponse(false);
    }

    /**
     * POST /api/v1/profesional-horarios/crear-para-recurso
     */
    public function actionCrearParaRecurso(): array
    {
        return $this->createResponse(true);
    }

    /**
     * PUT|PATCH /api/v1/profesional-horarios/actualizar/<id>
     *
     * @param int $id
     */
    public function actionActualizar($id): array
    {
        return $this->updateResponse((int) $id, false);
    }

    /**
     * PUT|PATCH /api/v1/profesional-horarios/actualizar-para-recurso/<id>
     *
     * @param int $id
     */
    public function actionActualizarParaRecurso($id): array
    {
        return $this->updateResponse((int) $id, true);
    }

    /**
     * DELETE /api/v1/profesional-horarios/eliminar/<id>
     *
     * @param int $id
     */
    public function actionEliminar($id): array
    {
        return $this->deleteResponse((int) $id, false);
    }

    /**
     * DELETE /api/v1/profesional-horarios/eliminar-para-recurso/<id>
     *
     * @param int $id
     */
    public function actionEliminarParaRecurso($id): array
    {
        return $this->deleteResponse((int) $id, true);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function listResponse(array $params): array
    {
        $rows = ProfesionalHorarioService::queryListado($params)->limit(500)->all();
        $data = [];
        foreach ($rows as $row) {
            $data[] = ProfesionalHorarioService::toApiArray($row);
        }

        return ['success' => true, 'data' => $data];
    }

    /**
     * @return array<string, mixed>
     */
    private function createResponse(bool $paraRecurso): array
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }
        $merged = array_merge(Yii::$app->request->get(), $body);

        if ($paraRecurso) {
            $idEfector = (int) ($merged['id_efector'] ?? 0);
            if ($idEfector <= 0) {
                throw new BadRequestHttpException('id_efector es requerido.');
            }
            $this->assertEfectorParamMatchesSessionWhenPresent($idEfector);
        } else {
            $idEfector = $this->requireEfectorId();
            $merged['id_persona'] = (int) Yii::$app->user->getIdPersona();
        }
        $merged['id_efector'] = $idEfector;

        $result = ProfesionalHorarioService::crear($merged);
        if (!$result['ok']) {
            return $this->error(
                'No se pudo crear el horario.',
                array_merge($result['errors'] ?? [], ['conflicts' => $result['conflicts'] ?? []]),
                422
            );
        }

        Yii::$app->response->statusCode = 201;

        return [
            'success' => true,
            'message' => 'Horario creado.',
            'data' => ProfesionalHorarioService::toApiArray($result['model']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateResponse(int $id, bool $paraRecurso): array
    {
        $model = $this->findOwned($id, $paraRecurso);
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }
        unset($body['id_efector'], $body['id_persona']);
        $body['id_efector'] = (int) $model->id_efector;
        $body['id_persona'] = (int) $model->id_persona;

        $result = ProfesionalHorarioService::actualizar($model, $body);
        if (!$result['ok']) {
            return $this->error(
                'No se pudo actualizar el horario.',
                array_merge($result['errors'] ?? [], ['conflicts' => $result['conflicts'] ?? []]),
                422
            );
        }

        return [
            'success' => true,
            'message' => 'Horario actualizado.',
            'data' => ProfesionalHorarioService::toApiArray($result['model']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteResponse(int $id, bool $paraRecurso): array
    {
        $model = $this->findOwned($id, $paraRecurso);
        $model->delete();

        return ['success' => true, 'message' => 'Horario eliminado.'];
    }

    private function findOwned(int $id, bool $paraRecurso): ProfesionalHorario
    {
        $idEfector = $this->requireEfectorId();
        /** @var ProfesionalHorario|null $model */
        $model = ProfesionalHorario::findOne(['id' => $id, 'id_efector' => $idEfector, 'deleted_at' => null]);
        if ($model === null) {
            throw new NotFoundHttpException('Horario no encontrado.');
        }
        if (!$paraRecurso && (int) $model->id_persona !== (int) Yii::$app->user->getIdPersona()) {
            throw new ForbiddenHttpException('No puede modificar Horarios de otro profesional.');
        }

        return $model;
    }

    private function requireEfectorId(): int
    {
        $id = (int) Yii::$app->user->getIdEfector();
        if ($id <= 0) {
            throw new BadRequestHttpException('Se requiere efector en sesión operativa.');
        }

        return $id;
    }

    private function assertEfectorParamMatchesSessionWhenPresent(int $idEfectorParam): void
    {
        $sessionEfector = (int) Yii::$app->user->getIdEfector();
        if ($sessionEfector > 0 && $sessionEfector !== $idEfectorParam) {
            throw new ForbiddenHttpException('El efector indicado no coincide con su sesión.');
        }
    }
}
