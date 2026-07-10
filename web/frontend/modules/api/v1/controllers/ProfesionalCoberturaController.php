<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use common\components\Domain\Organization\Service\ProfesionalCobertura\ProfesionalCoberturaActivaService;
use common\components\Domain\Organization\Service\ProfesionalCobertura\ProfesionalCoberturaService;
use common\components\Domain\Organization\Service\ProfesionalCobertura\ProfesionalCoberturaUiFlowService;
use common\models\ProfesionalCobertura;
use common\models\ProfesionalEfectorServicio;

/**
 * Cobertura / roster EMER e IMP (entrada–salida). No expone cupos a pacientes.
 *
 * **RBAC** `/api/profesional-cobertura/...` (sin v1):
 * - propio: listar, crear, actualizar, eliminar, gestionar, elegir-pes, listar-activas
 * - staff: *-para-recurso (+ id_efector / id_persona o PES)
 */
class ProfesionalCoberturaController extends BaseController
{
    /**
     * GET|POST /api/v1/profesional-cobertura/elegir-pes
     *
     * Lista PES del profesional (propio o staff) para asociar cobertura a un servicio.
     *
     * @action_name Elegir asignación PES para cobertura
     * @entity Coberturas
     * @tags cobertura,pes,servicio
     * @spa_presentation fullscreen
     */
    public function actionElegirPes(): array
    {
        $req = Yii::$app->request;
        $idEfector = $this->requireEfectorId();
        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        $modoStaff = ((string) ($fromClient['modo'] ?? '') === 'staff');

        $ui = \common\components\Platform\Ui\UiScreenService::handleScreen(
            'profesional-cobertura',
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
            $ui['action_id'] = 'profesional-cobertura.elegir-pes';
        }

        return $ui;
    }

    /**
     * GET|POST /api/v1/profesional-cobertura/gestionar
     *
     * @action_name Gestionar cobertura (guardia / internación)
     * @entity Coberturas
     * @tags cobertura,guardia,internacion,roster,agenda
     * @spa_presentation fullscreen
     */
    public function actionGestionar(): array
    {
        $req = Yii::$app->request;
        $idEfector = $this->requireEfectorId();
        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        $allowOwn = !((string) ($fromClient['modo'] ?? '') === 'staff');

        if ($req->isPost) {
            return ProfesionalCoberturaUiFlowService::handlePost($idEfector, $fromClient, $allowOwn);
        }

        return ProfesionalCoberturaUiFlowService::renderForm($idEfector, $fromClient, $allowOwn);
    }

    /**
     * GET /api/v1/profesional-cobertura/listar-activas
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
            'data' => ProfesionalCoberturaActivaService::panelPayload($idEfector, $class, $at),
        ];
    }

    /**
     * GET /api/v1/profesional-cobertura/listar
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
     * GET /api/v1/profesional-cobertura/listar-para-recurso
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
     * POST /api/v1/profesional-cobertura/crear
     */
    public function actionCrear(): array
    {
        return $this->createResponse(false);
    }

    /**
     * POST /api/v1/profesional-cobertura/crear-para-recurso
     */
    public function actionCrearParaRecurso(): array
    {
        return $this->createResponse(true);
    }

    /**
     * PUT|PATCH /api/v1/profesional-cobertura/actualizar/<id>
     *
     * @param int $id
     */
    public function actionActualizar($id): array
    {
        return $this->updateResponse((int) $id, false);
    }

    /**
     * PUT|PATCH /api/v1/profesional-cobertura/actualizar-para-recurso/<id>
     *
     * @param int $id
     */
    public function actionActualizarParaRecurso($id): array
    {
        return $this->updateResponse((int) $id, true);
    }

    /**
     * DELETE /api/v1/profesional-cobertura/eliminar/<id>
     *
     * @param int $id
     */
    public function actionEliminar($id): array
    {
        return $this->deleteResponse((int) $id, false);
    }

    /**
     * DELETE /api/v1/profesional-cobertura/eliminar-para-recurso/<id>
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
        $rows = ProfesionalCoberturaService::queryListado($params)->limit(500)->all();
        $data = [];
        foreach ($rows as $row) {
            $data[] = ProfesionalCoberturaService::toApiArray($row);
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

        $result = ProfesionalCoberturaService::crear($merged);
        if (!$result['ok']) {
            return $this->error(
                'No se pudo crear la cobertura.',
                array_merge($result['errors'] ?? [], ['conflicts' => $result['conflicts'] ?? []]),
                422
            );
        }

        Yii::$app->response->statusCode = 201;

        return [
            'success' => true,
            'message' => 'Cobertura creada.',
            'data' => ProfesionalCoberturaService::toApiArray($result['model']),
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

        $result = ProfesionalCoberturaService::actualizar($model, $body);
        if (!$result['ok']) {
            return $this->error(
                'No se pudo actualizar la cobertura.',
                array_merge($result['errors'] ?? [], ['conflicts' => $result['conflicts'] ?? []]),
                422
            );
        }

        return [
            'success' => true,
            'message' => 'Cobertura actualizada.',
            'data' => ProfesionalCoberturaService::toApiArray($result['model']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteResponse(int $id, bool $paraRecurso): array
    {
        $model = $this->findOwned($id, $paraRecurso);
        $model->delete();

        return ['success' => true, 'message' => 'Cobertura eliminada.'];
    }

    private function findOwned(int $id, bool $paraRecurso): ProfesionalCobertura
    {
        $idEfector = $this->requireEfectorId();
        /** @var ProfesionalCobertura|null $model */
        $model = ProfesionalCobertura::findOne(['id' => $id, 'id_efector' => $idEfector, 'deleted_at' => null]);
        if ($model === null) {
            throw new NotFoundHttpException('Cobertura no encontrada.');
        }
        if (!$paraRecurso && (int) $model->id_persona !== (int) Yii::$app->user->getIdPersona()) {
            throw new ForbiddenHttpException('No puede modificar coberturas de otro profesional.');
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
