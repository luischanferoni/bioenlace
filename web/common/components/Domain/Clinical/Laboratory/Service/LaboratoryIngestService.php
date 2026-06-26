<?php

namespace common\components\Domain\Clinical\Laboratory\Service;

use common\components\Domain\Clinical\Laboratory\Mapper\FhirDiagnosticReportMapper;
use common\components\Domain\Integrations\Laboratory\Contract\FhirLabResultsConnector;
use common\components\Domain\Integrations\Laboratory\LabConnectorRegistry;
use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\Observation;
use common\models\Person\Persona;
use Yii;

final class LaboratoryIngestService
{
    private FhirDiagnosticReportMapper $mapper;
    private LaboratoryEncounterLinkService $encounterLink;

    public function __construct(
        ?FhirDiagnosticReportMapper $mapper = null,
        ?LaboratoryEncounterLinkService $encounterLink = null
    ) {
        $this->mapper = $mapper ?? new FhirDiagnosticReportMapper();
        $this->encounterLink = $encounterLink ?? new LaboratoryEncounterLinkService();
    }

    /**
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function syncForPersona(int $idPersona, ?string $connectorKey = null): array
    {
        $persona = Persona::findOne($idPersona);
        if ($persona === null || $persona->documento === null || $persona->documento === '') {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Persona sin documento.']];
        }

        $connector = LabConnectorRegistry::get($connectorKey);
        $patientFhirId = $connector->resolvePatientFhirId((string) $persona->documento);
        if ($patientFhirId === null || $patientFhirId === '') {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Paciente no encontrado en el LIS.']];
        }

        $bundle = $connector->fetchDiagnosticReports($patientFhirId);
        $items = $this->mapper->extractReportsFromBundle($bundle);

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $source = $connector->getConnectorKey();

        foreach ($items as $item) {
            try {
                if ($this->upsertReport($idPersona, $source, $item['report'], $item['observations'])) {
                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                Yii::error($e, 'laboratory-ingest');
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param array<int, array<string, mixed>> $observations
     * @return bool True si el informe es nuevo (importado), false si ya existía (actualizado).
     */
    private function upsertReport(int $idPersona, string $source, array $report, array $observations): bool
    {
        $externalId = (string) ($report['id'] ?? '');
        if ($externalId === '') {
            return false;
        }

        $model = DiagnosticReport::findOne([
            'source_system' => $source,
            'external_id' => $externalId,
        ]) ?? new DiagnosticReport();

        $isNew = $model->isNewRecord;

        $code = $this->mapper->firstCoding($report['code'] ?? []);
        $model->subject_persona_id = $idPersona;
        $model->source_system = $source;
        $model->external_id = $externalId;
        $model->status = (string) ($report['status'] ?? 'final');
        $model->code = $code['code'];
        $model->code_system = $code['system'];
        $model->display = $code['display'] ?? ($report['code']['text'] ?? null);
        $model->issued_at = $report['issued'] ?? $report['effectiveDateTime'] ?? null;
        $model->conclusion = $this->extractConclusion($report);
        $model->encounter_id = $this->encounterLink->resolveEncounterId($idPersona, $report);
        $model->payload_json = json_encode($report, JSON_UNESCAPED_UNICODE);

        if (!$model->save()) {
            throw new \RuntimeException('DiagnosticReport: ' . json_encode($model->getErrors()));
        }

        foreach ($observations as $obsFhir) {
            $this->upsertObservation($idPersona, $source, (int) $model->id, $model->encounter_id, $obsFhir);
        }

        if ($isNew) {
            (new PostLabClassificationAgent())->runAfterIngest($model);
        }

        return $isNew;
    }

    /**
     * @param array<string, mixed> $obsFhir
     */
    private function upsertObservation(
        int $idPersona,
        string $source,
        int $diagnosticReportId,
        ?int $encounterId,
        array $obsFhir
    ): void {
        $externalId = (string) ($obsFhir['id'] ?? '');
        if ($externalId === '') {
            $externalId = $source . '-dr-' . $diagnosticReportId . '-obs-' . md5(json_encode($obsFhir));
        }

        $obs = Observation::findOne([
            'source_system' => $source,
            'external_id' => $externalId,
        ]) ?? new Observation();

        $code = $this->mapper->firstCoding($obsFhir['code'] ?? []);
        $obs->subject_persona_id = $idPersona;
        $obs->encounter_id = $encounterId;
        $obs->diagnostic_report_id = $diagnosticReportId;
        $obs->source_system = $source;
        $obs->external_id = $externalId;
        $obs->status = (string) ($obsFhir['status'] ?? 'final');
        $obs->category = Observation::CATEGORY_EXAM;
        $obs->code = $code['code'] ?? 'unknown';
        $obs->code_system = $code['system'];
        $obs->effective_datetime = $obsFhir['effectiveDateTime'] ?? null;

        $valueQty = $obsFhir['valueQuantity'] ?? null;
        if (is_array($valueQty)) {
            $obs->value_quantity = $valueQty['value'] ?? null;
            $obs->value_unit = $valueQty['unit'] ?? null;
        } else {
            $obs->value_string = is_string($obsFhir['valueString'] ?? null)
                ? $obsFhir['valueString']
                : (is_string($obsFhir['valueCodeableConcept']['text'] ?? null)
                    ? $obsFhir['valueCodeableConcept']['text']
                    : ($code['display'] ?? null));
        }

        $obs->interpretation_code = $this->extractInterpretationCode($obsFhir);
        [$refLow, $refHigh] = $this->extractReferenceRange($obsFhir);
        $obs->reference_range_low = $refLow;
        $obs->reference_range_high = $refHigh;

        if (!$obs->save()) {
            throw new \RuntimeException('Observation: ' . json_encode($obs->getErrors()));
        }
    }

    /**
     * @param array<string, mixed> $report
     */
    private function extractConclusion(array $report): ?string
    {
        $c = $report['conclusion'] ?? null;
        if (is_string($c)) {
            return $c;
        }
        if (is_array($c)) {
            if (isset($c['text']) && is_string($c['text'])) {
                return $c['text'];
            }
            if (isset($c[0]['text']) && is_string($c[0]['text'])) {
                return $c[0]['text'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $obsFhir
     */
    private function extractInterpretationCode(array $obsFhir): ?string
    {
        $block = $obsFhir['interpretation'] ?? null;
        if (!is_array($block) || $block === []) {
            return null;
        }
        $first = isset($block[0]) && is_array($block[0]) ? $block[0] : $block;
        if (isset($first['coding'][0]['code'])) {
            return strtoupper(trim((string) $first['coding'][0]['code']));
        }
        if (isset($first['text']) && is_string($first['text'])) {
            return strtoupper(trim($first['text']));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $obsFhir
     * @return array{0: float|null, 1: float|null}
     */
    private function extractReferenceRange(array $obsFhir): array
    {
        $rr = $obsFhir['referenceRange'][0] ?? null;
        if (!is_array($rr)) {
            return [null, null];
        }
        $low = isset($rr['low']['value']) && is_numeric($rr['low']['value']) ? (float) $rr['low']['value'] : null;
        $high = isset($rr['high']['value']) && is_numeric($rr['high']['value']) ? (float) $rr['high']['value'] : null;

        return [$low, $high];
    }
}
