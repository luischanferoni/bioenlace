<?php

namespace common\components\Clinical\CarePlan\Reminder;

use common\models\Clinical\CarePlanActivity;
use common\models\Clinical\MedicationRequest;
use common\models\Clinical\ServiceRequest;
use yii\db\Query;

/**
 * Asigna dosage_json.timing a medicación del care plan demo (desarrollo).
 */
final class CarePlanReminderDemoTimingService
{
    private const CARE_PLAN_SEED_TITLE = '[DEV] Care plan demo (app paciente)';

    /**
     * @param list<string> $timeOfDay ej. ['08:00', '20:00']
     * @return array{updated_medication: int, updated_service: int, care_plan_id: int|null}
     */
    public function applyTimingToDemoCarePlan(
        int $idPersona,
        array $timeOfDay = ['08:00', '20:00'],
        array $serviceTimeOfDay = ['07:00']
    ): array
    {
        $planId = (new Query())
            ->select('id')
            ->from('{{%care_plan}}')
            ->where([
                'subject_persona_id' => $idPersona,
                'title' => self::CARE_PLAN_SEED_TITLE,
                'deleted_at' => null,
            ])
            ->scalar();

        if (!$planId) {
            return ['updated_medication' => 0, 'updated_service' => 0, 'care_plan_id' => null];
        }

        $dosageJson = json_encode([
            'timing' => [
                'repeat' => [
                    'period' => 1,
                    'periodUnit' => 'd',
                    'timeOfDay' => array_values($timeOfDay),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $activityIds = CarePlanActivity::find()
            ->select('resource_id')
            ->where(['care_plan_id' => (int) $planId, 'kind' => 'medication-request'])
            ->column();

        $reminderJson = json_encode([
            'timing' => [
                'repeat' => [
                    'period' => 1,
                    'periodUnit' => 'd',
                    'timeOfDay' => array_values($serviceTimeOfDay),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $updatedMedication = 0;
        foreach ($activityIds as $mrId) {
            $mr = MedicationRequest::findOne((int) $mrId);
            if ($mr === null) {
                continue;
            }
            $mr->dosage_json = $dosageJson;
            if ($mr->save(false, ['dosage_json', 'updated_at'])) {
                $updatedMedication++;
            }
        }

        $srIds = CarePlanActivity::find()
            ->select('resource_id')
            ->where(['care_plan_id' => (int) $planId, 'kind' => 'service-request'])
            ->column();

        $updatedService = 0;
        foreach ($srIds as $srId) {
            $sr = ServiceRequest::findOne((int) $srId);
            if ($sr === null) {
                continue;
            }
            $sr->setAttribute('reminder_json', $reminderJson);
            if ($sr->save(false, ['reminder_json', 'updated_at'])) {
                $updatedService++;
            }
        }

        return [
            'updated_medication' => $updatedMedication,
            'updated_service' => $updatedService,
            'care_plan_id' => (int) $planId,
        ];
    }
}
