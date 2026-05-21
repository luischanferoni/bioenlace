<?php

namespace frontend\modules\api\v1\controllers\clinical;

use Yii;
use common\components\Assistant\EntryPoints\ClinicalEncounter\ClinicalEncounterEntry;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * API clínica — Encounter (documentación, análisis IA).
 *
 * POST /api/v1/clinical/encounter/analizar
 * POST /api/v1/clinical/encounter/guardar
 */
class EncounterController extends BaseController
{
    public static $authenticatorExcept = [];

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    public function actionAnalizar()
    {
        $out = ClinicalEncounterEntry::analizar(Yii::$app->request->getBodyParams());

        return $this->applyServiceHttpStatus($out);
    }

    public function actionGuardar()
    {
        $out = ClinicalEncounterEntry::guardar($this->mergeRequestBody());

        return $this->applyServiceHttpStatus($out);
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
