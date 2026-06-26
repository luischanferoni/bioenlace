<?php

namespace common\components\Domain\Clinical\Laboratory\Service;

use common\models\Clinical\Encounter;

/**
 * Resuelve encounter_id para un DiagnosticReport FHIR.
 * Delega en {@see LaboratoryEncounterLinkAgent} cuando el flag está activo.
 */
final class LaboratoryEncounterLinkService
{
    /**
     * @param array<string, mixed> $fhirResource DiagnosticReport
     * @param array<string, mixed> $reportMeta diagnostic_report_id, code, display, issued_at
     */
    public function resolveForIngest(int $subjectPersonaId, array $fhirResource, array $reportMeta): ?int
    {
        return (new LaboratoryEncounterLinkAgent())->resolveEncounterIdForIngest(
            $subjectPersonaId,
            $fhirResource,
            $reportMeta
        );
    }

    /**
     * @param array<string, mixed> $fhirResource DiagnosticReport
     * @deprecated Usar resolveForIngest tras persistir el informe.
     */
    public function resolveEncounterId(int $subjectPersonaId, array $fhirResource): ?int
    {
        return $this->resolveEncounterIdLegacy($subjectPersonaId, $fhirResource);
    }

    /**
     * @param array<string, mixed> $fhirResource
     */
    public function resolveEncounterIdLegacy(int $subjectPersonaId, array $fhirResource): ?int
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
