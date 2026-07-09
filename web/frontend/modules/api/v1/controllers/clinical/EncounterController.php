<?php

namespace frontend\modules\api\v1\controllers\clinical;

use Yii;
use common\components\Domain\Clinical\Assistant\ClinicalEncounterEntry;
use common\components\Domain\Clinical\Dto\MedicationRequestDto;
use common\components\Domain\Clinical\Dto\ServiceRequestDto;
use common\components\Domain\Clinical\Service\MedicationRequestService;
use common\components\Domain\Clinical\Service\ServiceRequestService;
use common\components\Platform\Ui\UiScreenService;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * API clínica — Encounter (documentación, análisis IA).
 *
 * POST /api/v1/clinical/encounter/analizar
 * POST /api/v1/clinical/encounter/guardar
 *
 * Especialidades (lectura): GET …/encounter/<id>/odontology | …/ophthalmology
 */
class EncounterController extends BaseController
{
    use ClinicalAccessTrait;

    public static $authenticatorExcept = [];

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    public function actionAnalizar()
    {
        $out = ClinicalEncounterEntry::analizar($this->mergeRequestBody());

        return $this->applyServiceHttpStatus($out);
    }

    public function actionGuardar()
    {
        $out = ClinicalEncounterEntry::guardar($this->mergeRequestBody());

        return $this->applyServiceHttpStatus($out);
    }

    /**
     * UI JSON: medicación y prácticas activas de un encounter (staff).
     *
     * GET /api/v1/clinical/encounter/listar-ordenes-activas?encounter_id=
     */
    public function actionListarOrdenesActivas(): array
    {
        $req = Yii::$app->request;
        $params = $req->get();
        $encounterId = (int) ($params['encounter_id'] ?? $params['id_consulta'] ?? 0);
        if ($encounterId <= 0) {
            return $this->clinicalError('Se requiere encounter_id en query.', null, 400);
        }

        [$encounter, $err] = $this->requireEncounterAccess($encounterId);
        if ($err !== null) {
            return $err;
        }

        $params['encounter_id'] = (string) $encounterId;

        $out = UiScreenService::handleScreen(
            'encounter',
            'listar-ordenes-activas',
            $params,
            $req->post(),
            static function (): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (($out['kind'] ?? '') !== 'ui_definition' || !$req->isGet) {
            return $out;
        }

        $medItems = [];
        foreach ((new MedicationRequestService())->listForEncounter($encounter->id) as $mr) {
            $dto = MedicationRequestDto::fromModel($mr)->toArray();
            $medItems[] = [
                'id' => (string) $mr->id,
                'name' => (string) ($dto['medicationDisplay'] ?? $dto['medicationCode'] ?? 'Medicación'),
                'subtitle' => (string) ($dto['dosageText'] ?? ''),
            ];
        }

        $practItems = [];
        foreach ((new ServiceRequestService())->listForEncounter($encounter->id) as $sr) {
            $dto = ServiceRequestDto::fromModel($sr)->toArray();
            $practItems[] = [
                'id' => (string) $sr->id,
                'name' => (string) ($dto['display'] ?? $dto['code'] ?? 'Indicación'),
                'subtitle' => (string) ($dto['category'] ?? ''),
            ];
        }

        $out = UiScreenService::withListBlockItems($out, $medItems, 'medicaciones');

        return UiScreenService::withListBlockItems($out, $practItems, 'practicas');
    }

    /**
     * @param array<string, mixed> $out
     * @return array<string, mixed>
     */
    private function applyServiceHttpStatus(array $out): array
    {
        if (!empty($out['__statusCode'])) {
            Yii::$app->response->statusCode = (int) $out['__statusCode'];
            unset($out['__statusCode']);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function mergeRequestBody(): array
    {
        $body = Yii::$app->request->getBodyParams();
        if (empty($body)) {
            $body = Yii::$app->request->post();
        }
        if (empty($body)) {
            $raw = Yii::$app->request->getRawBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $body = $decoded;
                }
            }
        }

        return is_array($body) ? $body : [];
    }
}
