<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\Laboratory\Service\LaboratoryReportPdfService;
use common\components\Clinical\Laboratory\Service\LaboratoryResultQueryService;
use common\components\Clinical\PatientSummary\PatientEncounterSummaryQueryService;
use common\components\Ui\UiScreenService;
use common\models\Clinical\DiagnosticReport;
use common\models\Person\Persona;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Resultados de laboratorio (lectura paciente + listado por encounter).
 *
 * GET|POST /api/v1/clinical/laboratory-result/mis-resultados-como-paciente (UI JSON listado paciente)
 * GET|POST /api/v1/clinical/laboratory-result/ver-informe-como-paciente (UI JSON detalle)
 * GET  /api/v1/clinical/laboratory-result/descargar-pdf-como-paciente?report_id=
 * GET  /api/v1/clinical/encounter/<encounterId>/laboratory-result
 *
 * Ingesta LIS: consola `php yii laboratory-sync/persona|lote` (ver docs/laboratorio/flows/ingesta-cron.md).
 */
class LaboratoryResultController extends BaseController
{
    use ClinicalAccessTrait;

    private LaboratoryResultQueryService $query;
    private LaboratoryReportPdfService $pdf;

    public function init()
    {
        parent::init();
        $this->query = new LaboratoryResultQueryService();
        $this->pdf = new LaboratoryReportPdfService();
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    /**
     * UI JSON: listado de informes del paciente (asistente / móvil). Solo lectura en BD local.
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
            'laboratory-result',
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

            $pdfPath = '/api/v1/clinical/laboratory-result/descargar-pdf-como-paciente?report_id=' . $reportId;

            $mensaje = $this->query->formatReportDetailMessage($serialized);
            $related = (new PatientEncounterSummaryQueryService())->getRelatedEncounterForLabReport(
                $idPersona,
                $reportId
            );
            if ($related !== null) {
                $mensaje .= "\n\n---\n";
                $mensaje .= 'Atención vinculada: ';
                if (!empty($related['efectorNombre'])) {
                    $mensaje .= $related['efectorNombre'];
                }
                if (!empty($related['periodEnd'])) {
                    $mensaje .= ' (' . $related['periodEnd'] . ')';
                }
                if (!empty($related['teaser'])) {
                    $mensaje .= "\n" . $related['teaser'];
                }
                if (($related['published'] ?? false) === true) {
                    $mensaje .= "\n(Abrí «Mis atenciones» en la app para ver el resumen completo.)";
                }
            }

            return UiScreenService::renderUiDefinition(
                'laboratory-result',
                'ver-informe-como-paciente',
                array_merge($req->get(), ['report_id' => $reportId]),
                [
                    'report_id' => (string) $reportId,
                    'detalle_mensaje' => $mensaje,
                    'pdf_url' => $pdfPath,
                    'filename' => 'informe-laboratorio-' . $reportId . '.pdf',
                    'related_encounter_id' => $related !== null ? (string) ($related['encounterId'] ?? '') : '',
                    'related_encounter_json' => $related !== null
                        ? json_encode($related, JSON_UNESCAPED_UNICODE)
                        : '',
                ]
            );
        }

        return UiScreenService::handleScreen(
            'laboratory-result',
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
     * GET /api/v1/clinical/laboratory-result/descargar-pdf-como-paciente?report_id=
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
