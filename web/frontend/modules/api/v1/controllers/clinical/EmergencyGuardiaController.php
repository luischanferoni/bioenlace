<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Emergency\GuardiaEfectorAccess;
use common\components\Emergency\GuardiaIngresoService;
use common\components\Emergency\GuardiaQueueService;
use common\components\Emergency\GuardiaTriageService;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;

/**
 * Urgencias / guardia: ingreso, triage y tablero operativo (staff / médico EMER).
 *
 * POST /api/v1/clinical/emergency-guardia/ingresar
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/registrar-triage
 * GET  /api/v1/clinical/emergency-guardia/tablero
 * GET  /api/v1/clinical/emergency-guardia/<guardiaId>/ver
 */
class EmergencyGuardiaController extends BaseController
{
    private GuardiaIngresoService $ingreso;
    private GuardiaTriageService $triage;
    private GuardiaQueueService $queue;

    public function init(): void
    {
        parent::init();
        $this->ingreso = new GuardiaIngresoService();
        $this->triage = new GuardiaTriageService();
        $this->queue = new GuardiaQueueService();
    }

    public function actionIngresar(): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) (Yii::$app->request->post('id_efector') ?? 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->ingreso->ingresar(Yii::$app->request->post(), $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 500);
        }

        return $this->success($data, 'Ingreso a guardia registrado', 201);
    }

    public function actionRegistrarTriage(int $guardiaId): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) (Yii::$app->request->post('id_efector') ?? Yii::$app->request->get('id_efector') ?? 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->triage->registrar($guardiaId, Yii::$app->request->post(), $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 500);
        }

        return $this->success($data, 'Triage registrado');
    }

    public function actionTablero(): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) Yii::$app->request->get('id_efector', 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $filters = [
                'circuito_estado' => Yii::$app->request->get('circuito_estado'),
                'sin_triage' => (bool) Yii::$app->request->get('sin_triage', false),
                'incluir_finalizados' => (bool) Yii::$app->request->get('incluir_finalizados', false),
            ];
            $data = $this->queue->tablero($idEfector, $filters);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Tablero de guardia');
    }

    public function actionVer(int $guardiaId): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) Yii::$app->request->get('id_efector', 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->queue->detalle($guardiaId, $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        if ($data === null) {
            return $this->error('Guardia no encontrada.', null, 404);
        }

        return $this->success($data, 'Detalle de guardia');
    }
}
