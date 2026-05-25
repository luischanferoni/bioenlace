<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Emergency\GuardiaEfectorAccess;
use common\components\Emergency\GuardiaIndicadoresService;
use common\components\Emergency\GuardiaIngresoService;
use common\components\Emergency\GuardiaOperacionService;
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
 * GET  /api/v1/clinical/emergency-guardia/indicadores-resumen
 * GET  /api/v1/clinical/emergency-guardia/listar-efectores-derivacion
 * GET  /api/v1/clinical/emergency-guardia/<guardiaId>/ver
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/asignar
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/iniciar-atencion
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/derivar
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/finalizar
 */
class EmergencyGuardiaController extends BaseController
{
    private GuardiaIngresoService $ingreso;
    private GuardiaTriageService $triage;
    private GuardiaQueueService $queue;
    private GuardiaOperacionService $operacion;
    private GuardiaIndicadoresService $indicadores;

    public function init(): void
    {
        parent::init();
        $this->ingreso = new GuardiaIngresoService();
        $this->triage = new GuardiaTriageService();
        $this->queue = new GuardiaQueueService();
        $this->operacion = new GuardiaOperacionService();
        $this->indicadores = new GuardiaIndicadoresService();
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

    public function actionListarEfectoresDerivacion(): array
    {
        try {
            GuardiaEfectorAccess::assertCanAccessEfector(
                GuardiaEfectorAccess::resolveIdEfector((int) Yii::$app->request->get('id_efector', 0) ?: null)
            );
            $data = $this->queue->listarEfectoresDerivacion();
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Efectores para derivación');
    }

    public function actionIndicadoresResumen(): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) Yii::$app->request->get('id_efector', 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->indicadores->resumen($idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Indicadores de guardia');
    }

    public function actionAsignar(int $guardiaId): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) (Yii::$app->request->post('id_efector') ?? 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $pesId = (int) (Yii::$app->request->post('id_profesional_efector_servicio') ?? 0);
            if ($pesId <= 0) {
                $resolved = GuardiaEfectorAccess::resolvePesId(null);
                if ($resolved === null) {
                    throw new \InvalidArgumentException('Se requiere id_profesional_efector_servicio.');
                }
                $pesId = $resolved;
            }
            $data = $this->operacion->asignar($guardiaId, $pesId, $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Profesional asignado');
    }

    public function actionIniciarAtencion(int $guardiaId): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) (Yii::$app->request->post('id_efector') ?? 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->operacion->iniciarAtencion($guardiaId, $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Atención iniciada');
    }

    public function actionDerivar(int $guardiaId): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) (Yii::$app->request->post('id_efector') ?? 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->operacion->derivar($guardiaId, Yii::$app->request->post(), $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Derivación registrada');
    }

    public function actionFinalizar(int $guardiaId): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) (Yii::$app->request->post('id_efector') ?? 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->operacion->finalizar($guardiaId, Yii::$app->request->post(), $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Egreso registrado');
    }
}
