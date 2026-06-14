<?php

namespace common\components\Domain\Clinical\CarePlan\Reminder;

use common\models\Clinical\PersonaCarePlanReminderPref;

final class CarePlanReminderPreferenceService
{
    /**
     * @return array{globalEnabled: bool, plans: array<int, bool>, items: array<int, array{enabled: bool, customTimes: list<string>}>}
     */
    public function exportForPersona(int $idPersona): array
    {
        $rows = PersonaCarePlanReminderPref::find()
            ->where(['id_persona' => $idPersona])
            ->all();

        $global = true;
        $plans = [];
        $items = [];

        foreach ($rows as $row) {
            $planId = $row->care_plan_id !== null ? (int) $row->care_plan_id : null;
            $activityId = $row->activity_id !== null ? (int) $row->activity_id : null;
            $enabled = (bool) $row->enabled;

            if ($planId === null && $activityId === null) {
                $global = $enabled;
                continue;
            }
            if ($planId !== null && $activityId === null) {
                $plans[$planId] = $enabled;
                continue;
            }
            if ($activityId !== null) {
                $custom = [];
                if ($row->custom_times_json !== null && $row->custom_times_json !== '') {
                    $decoded = json_decode($row->custom_times_json, true);
                    if (is_array($decoded)) {
                        $custom = array_values(array_map('strval', $decoded));
                    }
                }
                $items[$activityId] = [
                    'enabled' => $enabled,
                    'customTimes' => $custom,
                    'carePlanId' => $planId,
                ];
            }
        }

        return [
            'globalEnabled' => $global,
            'plans' => $plans,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    public function importForPersona(int $idPersona, array $body): void
    {
        $now = date('Y-m-d H:i:s');

        if (array_key_exists('globalEnabled', $body)) {
            $this->upsert($idPersona, null, null, (bool) $body['globalEnabled'], null, $now);
        }

        $plans = $body['plans'] ?? [];
        if (is_array($plans)) {
            foreach ($plans as $planId => $enabled) {
                $this->upsert($idPersona, (int) $planId, null, (bool) $enabled, null, $now);
            }
        }

        $items = $body['items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $activityId => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $planId = isset($item['carePlanId']) ? (int) $item['carePlanId'] : null;
                $enabled = (bool) ($item['enabled'] ?? true);
                $times = $item['customTimes'] ?? [];
                $json = is_array($times) && $times !== []
                    ? json_encode(array_values($times), JSON_UNESCAPED_UNICODE)
                    : null;
                $this->upsert($idPersona, $planId, (int) $activityId, $enabled, $json, $now);
            }
        }
    }

    private function upsert(
        int $idPersona,
        ?int $carePlanId,
        ?int $activityId,
        bool $enabled,
        ?string $customTimesJson,
        string $now
    ): void {
        $row = PersonaCarePlanReminderPref::findOne([
            'id_persona' => $idPersona,
            'care_plan_id' => $carePlanId,
            'activity_id' => $activityId,
        ]) ?? new PersonaCarePlanReminderPref();

        $row->id_persona = $idPersona;
        $row->care_plan_id = $carePlanId;
        $row->activity_id = $activityId;
        $row->enabled = $enabled;
        $row->custom_times_json = $customTimesJson;
        $row->updated_at = $now;
        $row->save(false);
    }
}
