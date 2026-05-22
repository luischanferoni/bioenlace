<?php

namespace common\components\Clinical\Laboratory\Service;

use common\models\Clinical\Encounter;

/**
 * Resuelve encounter_id para un DiagnosticReport FHIR.
 */
final class LaboratoryEncounterLinkService
{
    /**
     * @param array<string, mixed> $fhirResource DiagnosticReport
     */
    public function resolveEncounterId(int $subjectPersonaId, array $fhirResource): ?int
    {
        $encRef = $fhirResource['encounter']['reference'] ?? $fhirResource['context']['reference'] ?? null;
        if (is_string($encRef) && preg_match('/Encounter\/(\d+)/', $encRef, $m)) {
            $id = (int) $m[1];
            $enc = Encounter::findOne(['id' => $id, 'subject_persona_id' => $subjectPersonaId, 'deleted_at' => null]);
            if ($enc !== null) {
                return (int) $enc->id;
            }
        }

        $issued = $fhirResource['issued'] ?? $fhirResource['effectiveDateTime'] ?? null;
        if (!is_string($issued) || $issued === '') {
            return null;
        }

        $day = substr($issued, 0, 10);
        $enc = Encounter::find()
            ->andWhere([
                'subject_persona_id' => $subjectPersonaId,
                'deleted_at' => null,
            ])
            ->andWhere(['>=', 'period_start', $day . ' 00:00:00'])
            ->andWhere(['<=', 'period_start', $day . ' 23:59:59'])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $enc !== null ? (int) $enc->id : null;
    }
}
