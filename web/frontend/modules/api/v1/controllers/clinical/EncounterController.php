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
use yii\web\UploadedFile;

/**
 * API clínica — Encounter (documentación, análisis IA).
 *
 * POST /api/v1/clinical/encounter/analizar
 * POST /api/v1/clinical/encounter/guardar
 *
 * Pipeline por etapas (síncrono, sin jobs):
 * POST …/captura/crear-o-subir | …/transcribir | …/analizar | …/guardar | …/descartar
 * GET  …/captura/listar | …/ver | …/audio
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
     * POST multipart/json: crea captura y opcionalmente sube audio + transcript dispositivo.
     */
    public function actionCapturaCrearOSubir()
    {
        $body = $this->mergeRequestBody();
        $file = UploadedFile::getInstanceByName('file');
        $out = ClinicalEncounterEntry::capturaCrearOSubir($body, $file);

        return $this->applyServiceHttpStatus($out);
    }

    /** POST: STT síncrono desde audio ya subido. */
    public function actionCapturaTranscribir()
    {
        $out = ClinicalEncounterEntry::capturaTranscribir($this->mergeRequestBody());

        return $this->applyServiceHttpStatus($out);
    }

    /** POST: análisis IA síncrono desde transcript persistido. */
    public function actionCapturaAnalizar()
    {
        $out = ClinicalEncounterEntry::capturaAnalizar($this->mergeRequestBody());

        return $this->applyServiceHttpStatus($out);
    }

    /** POST: guardar clínico síncrono desde draft de análisis. */
    public function actionCapturaGuardar()
    {
        $out = ClinicalEncounterEntry::capturaGuardar($this->mergeRequestBody());

        return $this->applyServiceHttpStatus($out);
    }

    /** GET: listar capturas abiertas (cross-device). */
    public function actionCapturaListar()
    {
        $out = ClinicalEncounterEntry::capturaListar(Yii::$app->request->get());

        return $this->applyServiceHttpStatus($out);
    }

    /** GET: ver una captura (por id o client_capture_id). */
    public function actionCapturaVer()
    {
        $params = array_merge(Yii::$app->request->get(), $this->mergeRequestBody());
        $out = ClinicalEncounterEntry::capturaVer($params);

        return $this->applyServiceHttpStatus($out);
    }

    /** POST: descartar captura y borrar audio. */
    public function actionCapturaDescartar()
    {
        $out = ClinicalEncounterEntry::capturaDescartar($this->mergeRequestBody());

        return $this->applyServiceHttpStatus($out);
    }

    /** GET: descargar audio de una captura abierta. */
    public function actionCapturaAudio()
    {
        $out = ClinicalEncounterEntry::capturaAudio(Yii::$app->request->get());
        if (isset($out['success']) && $out['success'] === false) {
            return $this->applyServiceHttpStatus($out);
        }

        $path = (string) ($out['path'] ?? '');
        $mime = (string) ($out['mime'] ?? 'application/octet-stream');
        $filename = (string) ($out['filename'] ?? 'audio.m4a');
        if ($path === '' || !is_file($path)) {
            Yii::$app->response->statusCode = 404;

            return ['success' => false, 'message' => 'Archivo no encontrado'];
        }

        return Yii::$app->response->sendFile($path, $filename, [
            'mimeType' => $mime,
            'inline' => false,
        ]);
    }

    /**
     * UI JSON: medicación, prácticas realizadas e indicaciones activas de un encounter (staff).
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
        $indicacionItems = [];
        foreach ((new ServiceRequestService())->listForEncounter($encounter->id) as $sr) {
            $dto = ServiceRequestDto::fromModel($sr)->toArray();
            $category = mb_strtolower(trim((string) ($dto['category'] ?? '')));
            $item = [
                'id' => (string) $sr->id,
                'name' => (string) ($dto['display'] ?? $dto['code'] ?? 'Orden'),
                'subtitle' => (string) ($dto['category'] ?? ''),
            ];
            if (in_array($category, ['counseling', 'follow-up'], true)) {
                $indicacionItems[] = $item;
            } elseif ($category !== 'referral') {
                $practItems[] = $item;
            }
        }

        $out = UiScreenService::withListBlockItems($out, $medItems, 'medicaciones');
        $out = UiScreenService::withListBlockItems($out, $practItems, 'practicas');

        return UiScreenService::withListBlockItems($out, $indicacionItems, 'indicaciones');
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
