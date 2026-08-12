<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Emergency\Service\GuardiaClinicalSummaryService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaEfectorAccess;
use common\components\Domain\Clinical\Emergency\Service\GuardiaEgresoEstructuradoService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaIndicadoresExportService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaIndicadoresService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaIngresoService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaInternacionService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaOperacionService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaQueueService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaSlaService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaTriageService;
use common\components\Platform\Ui\UiScreenService;
use common\models\Emergency\GuardiaTriage;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Urgencias / guardia: ingreso, triage y tablero operativo (staff / médico EMER).
 *
 * POST /api/v1/clinical/emergency-guardia/ingresar
 * GET  /api/v1/clinical/emergency-guardia/buscar-persona-ingreso
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/registrar-triage
 * GET  /api/v1/clinical/emergency-guardia/indicadores-resumen
 * GET  /api/v1/clinical/emergency-guardia/listar-efectores-derivacion
 * GET  /api/v1/clinical/emergency-guardia/<guardiaId>/ver
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/asignar
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/iniciar-atencion
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/derivar
 * POST /api/v1/clinical/emergency-guardia/<guardiaId>/finalizar
 * GET|POST /api/v1/clinical/emergency-guardia/<guardiaId>/egreso-formulario (UI JSON egreso estructurado)
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
    use ClinicalAccessTrait;

    private GuardiaIngresoService $ingreso;
    private GuardiaTriageService $triage;
    private GuardiaQueueService $queue;
    private GuardiaOperacionService $operacion;
    private GuardiaIndicadoresService $indicadores;
    private GuardiaClinicalSummaryService $clinical;
    private GuardiaInternacionService $internacion;
    private GuardiaIndicadoresExportService $export;
    private GuardiaEgresoEstructuradoService $egreso;

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
        $this->egreso = new GuardiaEgresoEstructuradoService();
    }

    public function actionIngresar(): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
            $data = $this->ingreso->ingresar(Yii::$app->request->post(), $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 500);
        }

        return $this->success($data, 'Ingreso a guardia registrado', 201);
    }

    /**
     * Autocomplete de pacientes para admisión (excluye quienes ya están en cola).
     *
     * GET /api/v1/clinical/emergency-guardia/buscar-persona-ingreso
     *
     * @tags clinical, emergency-guardia, staff
     */
    public function actionBuscarPersonaIngreso(): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        $q = Yii::$app->request->get('q');
        $opts = $this->ingreso->buscarCandidatos($idEfector, is_string($q) ? $q : null);
        $results = [];
        foreach ($opts as $opt) {
            $results[] = [
                'id' => (string) ($opt['value'] ?? ''),
                'text' => (string) ($opt['label'] ?? ''),
            ];
        }

        return $this->success(['results' => $results], 'Candidatos de ingreso');
    }

    public function actionRegistrarTriage(int $guardiaId): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector('GuardiaEpisode.triage');
            $data = $this->triage->registrar($guardiaId, Yii::$app->request->post(), $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 500);
        }

        return $this->success($data, 'Triage registrado');
    }

    public function actionVer(int $guardiaId): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
            $data = $this->queue->detalle($guardiaId, $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        if ($data === null) {
            return $this->error('Guardia no encontrada.', null, 404);
        }

        return $this->success($data, 'Detalle de guardia');
    }

    public function actionListarEfectoresDerivacion(): array
    {
        try {
            $this->requireGuardiaEfector();
            $data = $this->queue->listarEfectoresDerivacion();
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success($data, 'Efectores para derivación');
    }

    public function actionIndicadoresResumen(): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector('GuardiaEpisode.view_board');
            $data = $this->indicadores->resumen($idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success($data, 'Indicadores de guardia');
    }

    public function actionAsignar(int $guardiaId): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
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
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success($data, 'Profesional asignado');
    }

    public function actionIniciarAtencion(int $guardiaId): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
            $data = $this->operacion->iniciarAtencion($guardiaId, $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success($data, 'Atención iniciada');
    }

    public function actionDerivar(int $guardiaId): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
            $data = $this->operacion->derivar($guardiaId, Yii::$app->request->post(), $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success($data, 'Derivación registrada');
    }

    public function actionFinalizar(int $guardiaId): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
            $data = $this->operacion->finalizar($guardiaId, Yii::$app->request->post(), $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success($data, 'Egreso registrado');
    }

    /**
     * Paciente se retiró (retiro / fuga / abandono).
     * GET|POST /api/v1/clinical/emergency-guardia/<guardiaId>/egreso-formulario
     */
    public function actionEgresoFormulario(int $guardiaId): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = $this->requireGuardiaEfector();
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        $out = UiScreenService::handleScreen(
            'emergency-guardia',
            'egreso-formulario',
            $req->get(),
            $req->post(),
            function (array $post) use ($guardiaId, $idEfector): array {
                $data = $this->egreso->registrar($guardiaId, $idEfector, $post);

                return [
                    'data' => $data,
                    'message' => (string) ($data['message'] ?? 'Egreso de guardia registrado'),
                ];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->getIsGet()) {
            try {
                $ctx = $this->egreso->contexto($guardiaId, $idEfector);
            } catch (\InvalidArgumentException $e) {
                return $this->error($e->getMessage(), null, 400);
            }

            $params = array_merge($req->get(), [
                'modo_egreso' => (string) ($ctx['modo_egreso'] ?? 'administrativo'),
                'fecha_fin' => date('Y-m-d'),
                'hora_fin' => date('H:i'),
                'resumen_texto' => (string) ($ctx['paciente_nombre'] ?? ''),
            ]);
            $out = UiScreenService::renderUiDefinition('emergency-guardia', 'egreso-formulario', $params, $params);
            $out = $this->egreso->shapeUiDefinition($out, $ctx);
            $out['data'] = $ctx;
        }

        return $out;
    }

    public function actionResumenClinico(int $guardiaId): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
            $data = $this->clinical->resumen($guardiaId, $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success($data, 'Resumen clínico de guardia');
    }

    public function actionCrearPedido(int $guardiaId): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
            $data = $this->clinical->crearPedido($guardiaId, $idEfector, Yii::$app->request->post());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success($data, 'Pedido registrado', 201);
    }

    public function actionSolicitarInternacion(int $guardiaId): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
            $idEfectorInternacion = (int) (
                Yii::$app->request->post('notificar_internacion_id_efector')
                ?? Yii::$app->request->post('id_efector_internacion')
                ?? $idEfector
            );
            $data = $this->internacion->solicitarInternacion($guardiaId, $idEfector, $idEfectorInternacion);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success($data, 'Internación solicitada');
    }

    public function actionSlaConfig(): array
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
            $data = (new GuardiaSlaService())->configForEfector($idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success($data, 'Configuración SLA de guardia');
    }

    public function actionIndicadoresExportCsv()
    {
        try {
            $idEfector = $this->requireGuardiaEfector();
            $built = $this->export->buildCsv(
                $idEfector,
                Yii::$app->request->get('fecha_desde'),
                Yii::$app->request->get('fecha_hasta')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
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
            $idEfector = $this->requireGuardiaEfector('GuardiaEpisode.triage');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
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
                $idEfector = $this->requireGuardiaEfector('GuardiaEpisode.triage');

                $data = $this->triage->registrar($guardiaId, [
                    'level' => (int) ($post['level'] ?? 3),
                    'reason_text' => (string) ($post['reason_text'] ?? ''),
                    'vitals' => $post['vitals'] ?? null,
                    'bp_sys' => $post['bp_sys'] ?? null,
                    'bp_dia' => $post['bp_dia'] ?? null,
                    'hr' => $post['hr'] ?? null,
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
                $defaults = ['guardia_id' => (string) $guardiaId, 'level' => '3'];
                $triageRow = GuardiaTriage::findOne(['guardia_id' => $guardiaId]);
                if ($triageRow !== null) {
                    $defaults['level'] = (string) (int) $triageRow->level;
                    $defaults['reason_text'] = (string) ($triageRow->reason_text ?? '');
                    $vitals = $triageRow->getVitalsArray() ?? [];
                    if (isset($vitals['bp_sys'])) {
                        $defaults['bp_sys'] = (string) $vitals['bp_sys'];
                    }
                    if (isset($vitals['bp_dia'])) {
                        $defaults['bp_dia'] = (string) $vitals['bp_dia'];
                    }
                    if (isset($vitals['hr'])) {
                        $defaults['hr'] = (string) $vitals['hr'];
                    }
                }
                $out = UiScreenService::renderUiDefinition(
                    'emergency-guardia',
                    'registrar-triage-formulario',
                    $req->get(),
                    $defaults
                );
                if (is_array($out)) {
                    $out['values'] = $defaults;
                    if ($triageRow !== null) {
                        $out['title'] = 'Editar triage';
                    }
                }
            }
        }

        return $out;
    }

    private function requireGuardiaEfector(string $operationKey = 'GuardiaEpisode.view_board'): int
    {
        return $this->resolveIdEfectorForDomainOperation($operationKey);
    }
}
