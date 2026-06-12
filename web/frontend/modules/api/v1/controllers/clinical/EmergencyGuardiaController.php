<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\Emergency\Service\GuardiaClinicalSummaryService;
use common\components\Clinical\Emergency\Service\GuardiaEfectorAccess;
use common\components\Clinical\Emergency\Service\GuardiaIndicadoresExportService;
use common\components\Clinical\Emergency\Service\GuardiaIndicadoresService;
use common\components\Clinical\Emergency\Service\GuardiaIngresoService;
use common\components\Clinical\Emergency\Service\GuardiaInternacionService;
use common\components\Clinical\Emergency\Service\GuardiaOperacionService;
use common\components\Clinical\Emergency\Service\GuardiaQueueService;
use common\components\Clinical\Emergency\Service\GuardiaSlaService;
use common\components\Clinical\Emergency\Service\GuardiaTriageService;
use common\components\Ui\UiScreenService;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\Response;

/**
 * Urgencias / guardia: ingreso, triage y tablero operativo (staff / médico EMER).
 *
 * POST /api/v1/clinical/emergency-guardia/ingresar
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/registrar-triage
 * GET  /api/v1/clinical/emergency-guardia/indicadores-resumen
 * GET  /api/v1/clinical/emergency-guardia/listar-efectores-derivacion
 * GET  /api/v1/clinical/emergency-guardia/<guardiaId>/ver
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/asignar
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/iniciar-atencion
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/derivar
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/finalizar
 * GET  /api/v1/clinical/emergency-guardia/<guardiaId>/resumen-clinico
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/crear-pedido
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/solicitar-internacion
 * GET  /api/v1/clinical/emergency-guardia/indicadores-export-csv
 * GET  /api/v1/clinical/emergency-guardia/sla-config
 * GET|POST /api/v1/clinical/emergency-guardia/elegir-paciente-triage (UI JSON)
 * GET|POST /api/v1/clinical/emergency-guardia/registrar-triage-formulario (UI JSON)
 */
class EmergencyGuardiaController extends BaseController
{
    private GuardiaIngresoService $ingreso;
    private GuardiaTriageService $triage;
    private GuardiaQueueService $queue;
    private GuardiaOperacionService $operacion;
    private GuardiaIndicadoresService $indicadores;
    private GuardiaClinicalSummaryService $clinical;
    private GuardiaInternacionService $internacion;
    private GuardiaIndicadoresExportService $export;

    public function init(): void
    {
        parent::init();
        $this->ingreso = new GuardiaIngresoService();
        $this->triage = new GuardiaTriageService();
        $this->queue = new GuardiaQueueService();
        $this->operacion = new GuardiaOperacionService();
        $this->indicadores = new GuardiaIndicadoresService();
        $this->clinical = new GuardiaClinicalSummaryService();
        $this->internacion = new GuardiaInternacionService();
        $this->export = new GuardiaIndicadoresExportService();
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

    public function actionResumenClinico(int $guardiaId): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) Yii::$app->request->get('id_efector', 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->clinical->resumen($guardiaId, $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Resumen clínico de guardia');
    }

    public function actionCrearPedido(int $guardiaId): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) (Yii::$app->request->post('id_efector') ?? 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->clinical->crearPedido($guardiaId, $idEfector, Yii::$app->request->post());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Pedido registrado', 201);
    }

    public function actionSolicitarInternacion(int $guardiaId): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) (Yii::$app->request->post('id_efector') ?? 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $idEfectorInternacion = (int) (
                Yii::$app->request->post('notificar_internacion_id_efector')
                ?? Yii::$app->request->post('id_efector_internacion')
                ?? $idEfector
            );
            $data = $this->internacion->solicitarInternacion($guardiaId, $idEfector, $idEfectorInternacion);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Internación solicitada');
    }

    public function actionSlaConfig(): array
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) Yii::$app->request->get('id_efector', 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $data = (new GuardiaSlaService())->configForEfector($idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Configuración SLA de guardia');
    }

    public function actionIndicadoresExportCsv()
    {
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) Yii::$app->request->get('id_efector', 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
            $built = $this->export->buildCsv(
                $idEfector,
                Yii::$app->request->get('fecha_desde'),
                Yii::$app->request->get('fecha_hasta')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="' . $built['filename'] . '"'
        );
        $response->content = "\xEF\xBB\xBF" . $built['content'];

        return $response;
    }

    /**
     * UI JSON: pacientes en guardia pendientes de triage (staff EMER).
     *
     * @tags clinical, emergency-guardia, staff, ui_json
     */
    public function actionElegirPacienteTriage(): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) ($req->get('id_efector') ?? 0) ?: null
            );
            GuardiaEfectorAccess::assertCanAccessEfector($idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        $out = UiScreenService::handleScreen(
            'emergency-guardia',
            'elegir-paciente-triage',
            $req->get(),
            $req->post(),
            static function (): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->getIsGet()) {
            $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                (int) ($req->get('id_efector') ?? 0) ?: null
            );
            $tablero = $this->queue->tablero($idEfector, ['sin_triage' => true, 'solo_activos' => true]);
            $items = [];
            foreach ($tablero['items'] as $row) {
                $paciente = $row['paciente'] ?? [];
                $nombre = $paciente['nombre_completo'] ?? 'Sin nombre';
                $min = (int) ($row['minutos_espera'] ?? 0);
                $items[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'name' => $nombre . ' · ' . $min . ' min',
                    'label' => $nombre,
                    'subtitle' => (string) ($paciente['documento'] ?? ''),
                ];
            }

            return UiScreenService::withListBlockItems($out, $items, 'guardias');
        }

        return $out;
    }

    /**
     * UI JSON: formulario de triage Manchester (staff).
     *
     * @tags clinical, emergency-guardia, staff, ui_json
     */
    public function actionRegistrarTriageFormulario(): array
    {
        $req = Yii::$app->request;
        $out = UiScreenService::handleScreen(
            'emergency-guardia',
            'registrar-triage-formulario',
            $req->get(),
            $req->post(),
            function (array $post): array {
                $guardiaId = (int) ($post['guardia_id'] ?? 0);
                if ($guardiaId <= 0) {
                    throw new \InvalidArgumentException('Se requiere guardia_id.');
                }
                $idEfector = GuardiaEfectorAccess::resolveIdEfector(
                    (int) ($post['id_efector'] ?? 0) ?: null
                );
                GuardiaEfectorAccess::assertCanAccessEfector($idEfector);

                $vitals = [];
                if (!empty($post['bp_sys'])) {
                    $vitals['bp_sys'] = (int) $post['bp_sys'];
                }
                if (!empty($post['bp_dia'])) {
                    $vitals['bp_dia'] = (int) $post['bp_dia'];
                }
                if (!empty($post['hr'])) {
                    $vitals['hr'] = (int) $post['hr'];
                }

                $data = $this->triage->registrar($guardiaId, [
                    'level' => (int) ($post['level'] ?? 3),
                    'reason_text' => (string) ($post['reason_text'] ?? ''),
                    'vitals' => $vitals,
                    'id_efector' => $idEfector,
                ], $idEfector);

                return [
                    'data' => $data,
                    'message' => 'Triage registrado correctamente.',
                ];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->getIsGet()) {
            $guardiaId = (int) ($req->get('guardia_id') ?? 0);
            if ($guardiaId > 0) {
                $out = UiScreenService::renderUiDefinition(
                    'emergency-guardia',
                    'registrar-triage-formulario',
                    $req->get(),
                    ['guardia_id' => (string) $guardiaId, 'level' => '3']
                );
            }
        }

        return $out;
    }
}
