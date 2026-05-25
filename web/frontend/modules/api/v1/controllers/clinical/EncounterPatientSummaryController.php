<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\PatientSummary\PatientEncounterSummaryQueryService;
use common\components\Clinical\PatientSummary\PatientEncounterSummaryUiFormatter;
use common\components\Ui\UiScreenService;
use Yii;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * Resumen de atención ambulatoria para el paciente autenticado.
 *
 * JSON:
 * GET /api/v1/clinical/encounter/listar-atenciones-como-paciente
 * GET /api/v1/clinical/encounter/ver-resumen-como-paciente?encounter_id=
 * GET /api/v1/clinical/encounter/ultima-atencion-como-paciente
 *
 * UI JSON (asistente):
 * GET|POST mis-atenciones-como-paciente, ver-resumen-atencion-como-paciente, ultima-atencion-ui-como-paciente
 */
class EncounterPatientSummaryController extends BaseController
{
    use ClinicalAccessTrait;

    private PatientEncounterSummaryQueryService $query;
    private PatientEncounterSummaryUiFormatter $uiFormatter;

    public function init()
    {
        parent::init();
        $this->query = new PatientEncounterSummaryQueryService();
        $this->uiFormatter = new PatientEncounterSummaryUiFormatter();
    }

    private function requirePatientPersona(): ?array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return [
                $this->clinicalError(
                    'Solo pacientes autenticados pueden acceder a resúmenes de atención.',
                    null,
                    400
                ),
                0,
            ];
        }

        return [null, $idPersona];
    }

    public function actionListarAtencionesComoPaciente(): array
    {
        [$err, $idPersona] = $this->requirePatientPersona();
        if ($err !== null) {
            return $err;
        }

        $limit = (int) Yii::$app->request->get('limit', 20);
        $offset = (int) Yii::$app->request->get('offset', 0);

        return [
            'success' => true,
            'message' => 'Atenciones publicadas',
            'data' => $this->query->listForPersona($idPersona, $limit, $offset),
        ];
    }

    public function actionVerResumenComoPaciente(): array
    {
        [$err, $idPersona] = $this->requirePatientPersona();
        if ($err !== null) {
            return $err;
        }

        $encounterId = (int) Yii::$app->request->get('encounter_id', 0);
        if ($encounterId <= 0) {
            return $this->clinicalError('Se requiere encounter_id.', null, 400);
        }

        $detail = $this->query->getDetailForPersona($idPersona, $encounterId);
        if ($detail === null) {
            return $this->clinicalError('Resumen no encontrado o aún no publicado.', null, 404);
        }

        return [
            'success' => true,
            'message' => 'Resumen de atención',
            'data' => $detail,
        ];
    }

    public function actionUltimaAtencionComoPaciente(): array
    {
        [$err, $idPersona] = $this->requirePatientPersona();
        if ($err !== null) {
            return $err;
        }

        $detail = $this->query->getLatestForPersona($idPersona);
        if ($detail === null) {
            return $this->clinicalError('No hay atenciones publicadas.', null, 404);
        }

        return [
            'success' => true,
            'message' => 'Última atención',
            'data' => $detail,
        ];
    }

    /**
     * UI JSON: listado de atenciones publicadas.
     */
    public function actionMisAtencionesComoPaciente(): array
    {
        [$err, $idPersona] = $this->requirePatientPersona();
        if ($err !== null) {
            return $err;
        }

        $req = Yii::$app->request;
        $out = UiScreenService::handleScreen(
            'encounter',
            'mis-atenciones-como-paciente',
            $req->get(),
            $req->post(),
            static function (): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->isGet) {
            $result = $this->query->listForPersona($idPersona, 50, 0);
            $items = [];
            foreach ($result['items'] as $row) {
                $label = (string) ($row['efectorNombre'] ?? 'Atención');
                if (!empty($row['profesionalDisplay'])) {
                    $label .= ' · ' . $row['profesionalDisplay'];
                }
                $items[] = [
                    'id' => (string) ($row['encounterId'] ?? ''),
                    'name' => $label,
                    'label' => $label,
                    'subtitle' => (string) ($row['publishedAt'] ?? ''),
                    'meta' => ['encounter_id' => $row['encounterId'] ?? null],
                ];
            }

            return UiScreenService::withListBlockItems($out, $items, 'atenciones');
        }

        return $out;
    }

    /**
     * UI JSON: detalle de una atención (requiere encounter_id en POST).
     */
    public function actionVerResumenAtencionComoPaciente(): array
    {
        [$err, $idPersona] = $this->requirePatientPersona();
        if ($err !== null) {
            return $err;
        }

        $req = Yii::$app->request;
        $encounterId = (int) ($req->get('encounter_id') ?? $req->post('encounter_id') ?? 0);

        if (!$req->isPost) {
            if ($encounterId <= 0) {
                throw new \InvalidArgumentException('Seleccioná una atención de la lista.');
            }
            $detail = $this->query->getDetailForPersona($idPersona, $encounterId);
            if ($detail === null) {
                throw new \InvalidArgumentException('Resumen no encontrado o aún no publicado.');
            }

            return UiScreenService::renderUiDefinition(
                'encounter',
                'ver-resumen-atencion-como-paciente',
                array_merge($req->get(), ['encounter_id' => $encounterId]),
                [
                    'encounter_id' => (string) $encounterId,
                    'resumen_mensaje' => $this->uiFormatter->formatDetailMessage($detail),
                    'detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE),
                ]
            );
        }

        return UiScreenService::handleScreen(
            'encounter',
            'ver-resumen-atencion-como-paciente',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true, 'encounter_id' => (int) ($post['encounter_id'] ?? 0)]];
            }
        );
    }

    /**
     * UI JSON: abre directamente la última atención publicada.
     */
    public function actionUltimaAtencionUiComoPaciente(): array
    {
        [$err, $idPersona] = $this->requirePatientPersona();
        if ($err !== null) {
            return $err;
        }

        $req = Yii::$app->request;
        $detail = $this->query->getLatestForPersona($idPersona);
        if ($detail === null) {
            throw new \InvalidArgumentException('No hay atenciones publicadas todavía.');
        }

        $encounterId = (int) ($detail['encounterId'] ?? 0);

        if (!$req->isPost) {
            return UiScreenService::renderUiDefinition(
                'encounter',
                'ver-resumen-atencion-como-paciente',
                array_merge($req->get(), ['encounter_id' => $encounterId]),
                [
                    'encounter_id' => (string) $encounterId,
                    'resumen_mensaje' => $this->uiFormatter->formatDetailMessage($detail),
                    'detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE),
                ]
            );
        }

        return [
            'success' => true,
            'message' => 'Última atención',
            'data' => $detail,
        ];
    }
}
