<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\Laboratory\Service\LaboratoryIngestService;
use common\components\Clinical\Laboratory\Service\LaboratoryReportPdfService;
use common\components\Clinical\Laboratory\Service\LaboratoryResultQueryService;
use common\components\Ui\UiScreenService;
use common\models\Clinical\DiagnosticReport;
use common\models\Person\Persona;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Resultados de laboratorio (ingesta pull + lectura).
 *
 * GET  /api/v1/clinical/laboratory-results/mis-resultados
 * GET|POST /api/v1/clinical/laboratory-results/mis-resultados-como-paciente (UI JSON)
 * POST /api/v1/clinical/laboratory-results/sincronizar
 * GET|POST /api/v1/clinical/laboratory-results/sincronizar-como-paciente (UI JSON)
 * GET|POST /api/v1/clinical/laboratory-results/ver-informe-como-paciente (UI JSON detalle)
 * GET  /api/v1/clinical/laboratory-results/descargar-pdf-como-paciente?report_id=
 * GET  /api/v1/clinical/encounter/<encounterId>/laboratory-results
 */
class LaboratoryResultController extends BaseController
{
    use ClinicalAccessTrait;

    private LaboratoryResultQueryService $query;
    private LaboratoryIngestService $ingest;
    private LaboratoryReportPdfService $pdf;

    public function init()
    {
        parent::init();
        $this->query = new LaboratoryResultQueryService();
        $this->ingest = new LaboratoryIngestService();
        $this->pdf = new LaboratoryReportPdfService();
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    /**
     * Listado de informes del paciente autenticado.
     */
    public function actionMisResultados(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        return [
            'success' => true,
            'message' => 'Resultados de laboratorio',
            'data' => [
                'reports' => $this->query->listForPersona($idPersona),
            ],
        ];
    }

    /**
     * Pull desde LIS configurado (paciente autenticado = su persona).
     */
    public function actionSincronizar(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        $connectorKey = Yii::$app->request->post('connector')
            ?? Yii::$app->request->get('connector');

        try {
            $result = $this->ingest->syncForPersona($idPersona, is_string($connectorKey) ? $connectorKey : null);
        } catch (\Throwable $e) {
            Yii::error($e, 'laboratory-sync');

            return $this->clinicalError($e->getMessage(), null, 502);
        }

        return [
            'success' => true,
            'message' => 'Sincronización de laboratorio',
            'data' => $result,
        ];
    }

    /**
     * UI JSON: listado de informes del paciente (asistente / móvil).
     *
     * @tags clinical, laboratory, paciente, ui_json
     * @keywords mis resultados, laboratorio, análisis, estudios
     */
    public function actionMisResultadosComoPaciente(): array
    {
        $req = Yii::$app->request;
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        $out = UiScreenService::handleScreen(
            'laboratory-results',
            'mis-resultados-como-paciente',
            $req->get(),
            $req->post(),
            static function (): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->isGet) {
            $items = [];
            foreach ($this->query->listForPersona($idPersona) as $report) {
                $label = (string) ($report['display'] ?? 'Informe de laboratorio');
                $issued = (string) ($report['issuedAt'] ?? '');
                if ($issued !== '') {
                    $label .= ' · ' . $issued;
                }
                $obs = $report['observations'] ?? [];
                $subtitle = is_array($obs) && $obs !== [] ? count($obs) . ' analitos' : '';
                $items[] = [
                    'id' => (string) ($report['id'] ?? ''),
                    'name' => $label,
                    'label' => $label,
                    'subtitle' => $subtitle,
                ];
            }

            return UiScreenService::withListBlockItems($out, $items);
        }

        return $out;
    }

    /**
     * UI JSON: sincronizar resultados desde el LIS (asistente / móvil).
     *
     * @tags clinical, laboratory, paciente, ui_json
     * @keywords actualizar resultados, sincronizar laboratorio, traer análisis
     */
    public function actionSincronizarComoPaciente(): array
    {
        $req = Yii::$app->request;
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        return UiScreenService::handleScreen(
            'laboratory-results',
            'sincronizar-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($idPersona, $req): array {
                $connectorKey = $post['connector'] ?? $req->get('connector');
                try {
                    $result = $this->ingest->syncForPersona(
                        $idPersona,
                        is_string($connectorKey) && $connectorKey !== '' ? $connectorKey : null
                    );
                } catch (\Throwable $e) {
                    Yii::error($e, 'laboratory-sync');
                    throw new \RuntimeException($e->getMessage());
                }

                $msg = 'Se importaron ' . (int) ($result['imported'] ?? 0) . ' informe(s).';
                $errors = $result['errors'] ?? [];
                if (is_array($errors) && $errors !== []) {
                    $msg .= ' ' . implode(' ', array_map('strval', $errors));
                }

                return [
                    'data' => [
                        'success' => true,
                        'message' => $msg,
                        'sync' => $result,
                    ],
                ];
            }
        );
    }

    /**
     * UI JSON: detalle de un informe (paso 2 del flow paciente).
     *
     * @tags clinical, laboratory, paciente, ui_json
     */
    public function actionVerInformeComoPaciente(): array
    {
        $req = Yii::$app->request;
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        $reportId = (int) ($req->get('report_id') ?? $req->post('report_id') ?? 0);

        if (!$req->isPost) {
            if ($reportId <= 0) {
                throw new \InvalidArgumentException('Seleccioná un informe de la lista.');
            }
            $serialized = $this->query->getReportForPersona($idPersona, $reportId);
            if ($serialized === null) {
                throw new \InvalidArgumentException('Informe no encontrado.');
            }

            $pdfPath = '/api/v1/clinical/laboratory-results/descargar-pdf-como-paciente?report_id=' . $reportId;

            return UiScreenService::renderUiDefinition(
                'laboratory-results',
                'ver-informe-como-paciente',
                array_merge($req->get(), ['report_id' => $reportId]),
                [
                    'report_id' => (string) $reportId,
                    'titulo_informe' => (string) ($serialized['display'] ?? 'Informe de laboratorio'),
                    'fecha_informe' => (string) ($serialized['issuedAt'] ?? ''),
                    'analitos_texto' => $this->query->formatAnalitosText($serialized),
                    'conclusion' => (string) ($serialized['conclusion'] ?? ''),
                    'pdf_url' => $pdfPath,
                    'filename' => 'informe-laboratorio-' . $reportId . '.pdf',
                ]
            );
        }

        return UiScreenService::handleScreen(
            'laboratory-results',
            'ver-informe-como-paciente',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true, 'report_id' => (int) ($post['report_id'] ?? 0)]];
            }
        );
    }

    /**
     * Descarga PDF generado en servidor para un informe del paciente autenticado.
     *
     * GET /api/v1/clinical/laboratory-results/descargar-pdf-como-paciente?report_id=
     */
    public function actionDescargarPdfComoPaciente()
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new NotFoundHttpException('Solo pacientes autenticados.');
        }

        $reportId = (int) Yii::$app->request->get('report_id');
        if ($reportId <= 0) {
            throw new NotFoundHttpException('report_id requerido.');
        }

        $model = DiagnosticReport::findOne([
            'id' => $reportId,
            'subject_persona_id' => $idPersona,
            'deleted_at' => null,
        ]);
        if ($model === null) {
            throw new NotFoundHttpException('Informe no encontrado.');
        }

        $serialized = $this->query->getReportForPersona($idPersona, $reportId);
        $persona = Persona::findOne($idPersona);
        $binary = $this->pdf->renderBinary($model, $serialized ?? [], $persona);

        $filename = 'informe-laboratorio-' . $reportId . '.pdf';
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->content = $binary;

        return $response;
    }

    /**
     * Informes vinculados a un encounter (staff o paciente con acceso).
     */
    public function actionPorEncounter($encounterId): array
    {
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

        return [
            'success' => true,
            'message' => 'Laboratorio del encounter',
            'data' => [
                'encounterId' => (int) $encounter->id,
                'reports' => $this->query->listForEncounter((int) $encounter->id),
            ],
        ];
    }
}
