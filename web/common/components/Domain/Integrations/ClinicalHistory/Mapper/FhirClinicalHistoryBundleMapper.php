<?php

namespace common\components\Domain\Integrations\ClinicalHistory\Mapper;

use common\models\Clinical\AllergyIntolerance;
use common\models\Clinical\Condition;
use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\Encounter;
use common\models\Clinical\MedicationRequest;
use common\models\Clinical\ServiceRequest;
use common\models\Efector;
use common\models\Persona;
use common\models\ProfesionalEfectorServicio;

/**
 * Arma Bundle FHIR documental para export interoperable.
 *
 * @see web/docs/plans/interoperabilidad-historia-clinica/phases/02-mapper-fhir-bundle.md
 */
final class FhirClinicalHistoryBundleMapper
{
    public const PROFILE_ENCOUNTER_DOCUMENT_V1 = 'encounter-document-v1';

    private const ALLERGY_SNAPSHOT_LIMIT = 20;

    /**
     * @return array<string, mixed> Bundle FHIR type=document
     */
    public function buildForEncounter(Encounter $encounter, string $profile = self::PROFILE_ENCOUNTER_DOCUMENT_V1): array
    {
        $encounterId = (int) $encounter->id;
        $personaId = (int) $encounter->subject_persona_id;
        $persona = Persona::findOne(['id_persona' => $personaId]);
        $timestamp = gmdate('c');
        $bundleId = 'bioenlace-encounter-' . $encounterId;

        $patientRef = 'Patient/' . $personaId;
        $encounterRef = 'Encounter/' . $encounterId;

        $entries = [];
        $compositionSections = [];

        $patient = $this->mapPatient($persona, $personaId);
        $entries[] = $this->entry('patient', $patient);

        $encounterResource = $this->mapEncounter($encounter, $patientRef);
        $entries[] = $this->entry('encounter', $encounterResource);

        $org = $this->mapOrganization($encounter);
        if ($org !== null) {
            $entries[] = $this->entry('organization', $org);
        }

        $practitioner = $this->mapPractitioner($encounter);
        if ($practitioner !== null) {
            $entries[] = $this->entry('practitioner', $practitioner);
        }

        $compositionSections[] = $this->textSection(
            'Evolución',
            (string) ($encounter->note ?? $encounter->reason_text ?? '')
        );

        if ($encounter->reason_text) {
            $compositionSections[] = $this->textSection('Motivo', (string) $encounter->reason_text);
        }

        $conditionRefs = [];
        foreach ($this->loadConditions($encounterId) as $condition) {
            $resource = $this->mapCondition($condition, $patientRef, $encounterRef);
            $entries[] = $this->entry('condition-' . (int) $condition->id, $resource);
            $conditionRefs[] = ['reference' => 'Condition/' . (int) $condition->id];
        }
        if ($conditionRefs !== []) {
            $compositionSections[] = [
                'title' => 'Diagnósticos',
                'entry' => $conditionRefs,
            ];
        }

        $medRefs = [];
        foreach ($this->loadMedicationRequests($encounterId) as $med) {
            $resource = $this->mapMedicationRequest($med, $patientRef, $encounterRef);
            $entries[] = $this->entry('medication-' . (int) $med->id, $resource);
            $medRefs[] = ['reference' => 'MedicationRequest/' . (int) $med->id];
        }
        if ($medRefs !== []) {
            $compositionSections[] = [
                'title' => 'Medicación',
                'entry' => $medRefs,
            ];
        }

        $serviceRefs = [];
        foreach ($this->loadServiceRequests($encounterId) as $sr) {
            $resource = $this->mapServiceRequest($sr, $patientRef, $encounterRef);
            $entries[] = $this->entry('service-' . (int) $sr->id, $resource);
            $serviceRefs[] = ['reference' => 'ServiceRequest/' . (int) $sr->id];
        }
        if ($serviceRefs !== []) {
            $compositionSections[] = [
                'title' => 'Pedidos',
                'entry' => $serviceRefs,
            ];
        }

        $allergyRefs = [];
        foreach (AllergyIntolerance::findActiveBySubject($personaId, self::ALLERGY_SNAPSHOT_LIMIT) as $allergy) {
            $resource = $this->mapAllergy($allergy, $patientRef);
            $entries[] = $this->entry('allergy-' . (int) $allergy->id, $resource);
            $allergyRefs[] = ['reference' => 'AllergyIntolerance/' . (int) $allergy->id];
        }
        if ($allergyRefs !== []) {
            $compositionSections[] = [
                'title' => 'Alergias e intolerancias',
                'entry' => $allergyRefs,
            ];
        }

        $labRefs = [];
        foreach ($this->loadDiagnosticReports($encounterId, $personaId) as $report) {
            $resource = $this->mapDiagnosticReport($report, $patientRef, $encounterRef);
            $entries[] = $this->entry('diagnostic-' . (int) $report->id, $resource);
            $labRefs[] = ['reference' => 'DiagnosticReport/' . (int) $report->id];
        }
        if ($labRefs !== []) {
            $compositionSections[] = [
                'title' => 'Resultados de laboratorio',
                'entry' => $labRefs,
            ];
        }

        $composition = [
            'resourceType' => 'Composition',
            'id' => 'composition-' . $encounterId,
            'status' => 'final',
            'type' => [
                'coding' => [[
                    'system' => 'http://loinc.org',
                    'code' => '11506-3',
                    'display' => 'Progress note',
                ]],
            ],
            'subject' => ['reference' => $patientRef],
            'encounter' => ['reference' => $encounterRef],
            'date' => $encounter->period_end ?? $timestamp,
            'title' => 'Nota de atención Bioenlace',
            'author' => $practitioner !== null
                ? [['reference' => 'Practitioner/' . (int) $encounter->id_profesional_efector_servicio]]
                : [],
            'custodian' => $org !== null
                ? ['reference' => 'Organization/' . (int) $encounter->efector_id]
                : null,
            'section' => $compositionSections,
        ];
        $composition = array_filter($composition, static fn ($v) => $v !== null);

        array_unshift($entries, $this->entry('composition', $composition));

        return [
            'resourceType' => 'Bundle',
            'type' => 'document',
            'timestamp' => $timestamp,
            'identifier' => [
                'system' => 'urn:bioenlace:clinical-history-bundle',
                'value' => $bundleId,
            ],
            'meta' => [
                'tag' => [[
                    'system' => 'urn:bioenlace:exchange-profile',
                    'code' => $profile,
                ]],
            ],
            'entry' => $entries,
        ];
    }

    /**
     * @param array<string, mixed> $resource
     * @return array<string, mixed>
     */
    private function entry(string $key, array $resource): array
    {
        $type = (string) ($resource['resourceType'] ?? 'Resource');
        $id = (string) ($resource['id'] ?? $key);

        return [
            'fullUrl' => 'urn:uuid:bioenlace-' . $type . '-' . $id,
            'resource' => $resource,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPatient(?Persona $persona, int $personaId): array
    {
        return [
            'resourceType' => 'Patient',
            'id' => (string) $personaId,
            'identifier' => $this->patientIdentifiers($persona),
            'name' => $this->patientName($persona),
            'gender' => $this->mapGender($persona),
            'birthDate' => ($persona !== null && $persona->fecha_nacimiento)
                ? substr((string) $persona->fecha_nacimiento, 0, 10)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEncounter(Encounter $encounter, string $patientRef): array
    {
        return array_filter([
            'resourceType' => 'Encounter',
            'id' => (string) (int) $encounter->id,
            'status' => $encounter->status,
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => $encounter->encounter_class,
            ],
            'subject' => ['reference' => $patientRef],
            'serviceProvider' => $encounter->efector_id
                ? ['reference' => 'Organization/' . (int) $encounter->efector_id]
                : null,
            'period' => array_filter([
                'start' => $encounter->period_start,
                'end' => $encounter->period_end,
            ]),
            'reasonCode' => $encounter->reason_text
                ? [['text' => (string) $encounter->reason_text]]
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapOrganization(Encounter $encounter): ?array
    {
        if ($encounter->efector_id === null || (int) $encounter->efector_id <= 0) {
            return null;
        }
        $efector = Efector::findOne((int) $encounter->efector_id);
        if ($efector === null) {
            return null;
        }

        return [
            'resourceType' => 'Organization',
            'id' => (string) (int) $efector->id_efector,
            'name' => (string) $efector->nombre,
            'identifier' => array_filter([[
                'system' => 'urn:bioenlace:efector',
                'value' => (string) ($efector->codigo_sisa ?? $efector->id_efector),
            ]]),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapPractitioner(Encounter $encounter): ?array
    {
        $pesId = (int) ($encounter->id_profesional_efector_servicio ?? 0);
        if ($pesId <= 0) {
            return null;
        }
        $pes = ProfesionalEfectorServicio::findOne($pesId);
        if ($pes === null) {
            return null;
        }
        $prof = Persona::findOne(['id_persona' => (int) $pes->id_persona]);

        return array_filter([
            'resourceType' => 'Practitioner',
            'id' => (string) $pesId,
            'name' => $prof ? $this->patientName($prof) : [],
            'identifier' => [[
                'system' => 'urn:bioenlace:pes',
                'value' => (string) $pesId,
            ]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCondition(Condition $condition, string $patientRef, string $encounterRef): array
    {
        return [
            'resourceType' => 'Condition',
            'id' => (string) (int) $condition->id,
            'clinicalStatus' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code' => (string) $condition->clinical_status,
                ]],
            ],
            'verificationStatus' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                    'code' => (string) $condition->verification_status,
                ]],
            ],
            'code' => [
                'coding' => [[
                    'system' => (string) ($condition->code_system ?: 'http://hl7.org/fhir/sid/icd-10'),
                    'code' => (string) $condition->code,
                    'display' => (string) ($condition->display ?? ''),
                ]],
            ],
            'subject' => ['reference' => $patientRef],
            'encounter' => ['reference' => $encounterRef],
            'recordedDate' => $condition->recorded_date,
            'note' => $condition->note ? [['text' => (string) $condition->note]] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMedicationRequest(
        MedicationRequest $med,
        string $patientRef,
        string $encounterRef
    ): array {
        return array_filter([
            'resourceType' => 'MedicationRequest',
            'id' => (string) (int) $med->id,
            'status' => (string) $med->status,
            'intent' => (string) $med->intent,
            'medicationCodeableConcept' => [
                'coding' => [[
                    'system' => 'urn:bioenlace:medication',
                    'code' => (string) ($med->medication_code ?? ''),
                    'display' => (string) ($med->medication_display ?? ''),
                ]],
            ],
            'subject' => ['reference' => $patientRef],
            'encounter' => ['reference' => $encounterRef],
            'authoredOn' => $med->authored_on,
            'dosageInstruction' => $med->dosage_text
                ? [['text' => (string) $med->dosage_text]]
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapServiceRequest(
        ServiceRequest $sr,
        string $patientRef,
        string $encounterRef
    ): array {
        return array_filter([
            'resourceType' => 'ServiceRequest',
            'id' => (string) (int) $sr->id,
            'status' => (string) $sr->status,
            'intent' => (string) $sr->intent,
            'category' => [[
                'coding' => [[
                    'system' => 'urn:bioenlace:service-category',
                    'code' => (string) $sr->category,
                ]],
            ]],
            'code' => [
                'coding' => [[
                    'system' => (string) ($sr->code_system ?: 'urn:bioenlace:service'),
                    'code' => (string) ($sr->code ?? ''),
                    'display' => (string) ($sr->display ?? ''),
                ]],
            ],
            'subject' => ['reference' => $patientRef],
            'encounter' => ['reference' => $encounterRef],
            'occurrenceDateTime' => $sr->occurrence_datetime,
            'note' => $sr->note ? [['text' => (string) $sr->note]] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAllergy(AllergyIntolerance $allergy, string $patientRef): array
    {
        return array_filter([
            'resourceType' => 'AllergyIntolerance',
            'id' => (string) (int) $allergy->id,
            'clinicalStatus' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical',
                    'code' => (string) $allergy->clinical_status,
                ]],
            ],
            'verificationStatus' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/allergyintolerance-verification',
                    'code' => (string) $allergy->verification_status,
                ]],
            ],
            'type' => $allergy->type,
            'category' => $allergy->category ? [(string) $allergy->category] : null,
            'criticality' => $allergy->criticality,
            'code' => [
                'coding' => [[
                    'system' => 'urn:bioenlace:allergy',
                    'code' => (string) ($allergy->code ?? ''),
                    'display' => (string) ($allergy->display ?? ''),
                ]],
            ],
            'patient' => ['reference' => $patientRef],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDiagnosticReport(
        DiagnosticReport $report,
        string $patientRef,
        string $encounterRef
    ): array {
        return array_filter([
            'resourceType' => 'DiagnosticReport',
            'id' => (string) (int) $report->id,
            'status' => (string) $report->status,
            'code' => [
                'coding' => [[
                    'system' => (string) ($report->code_system ?: 'urn:bioenlace:lab'),
                    'code' => (string) ($report->code ?? $report->external_id),
                    'display' => (string) ($report->display ?? ''),
                ]],
            ],
            'subject' => ['reference' => $patientRef],
            'encounter' => $report->encounter_id ? ['reference' => $encounterRef] : null,
            'issued' => $report->issued_at,
            'conclusion' => $report->conclusion,
            'identifier' => [[
                'system' => 'urn:bioenlace:' . $report->source_system,
                'value' => (string) $report->external_id,
            ]],
        ]);
    }

    /**
     * @return Condition[]
     */
    private function loadConditions(int $encounterId): array
    {
        return Condition::find()
            ->andWhere(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    /**
     * @return MedicationRequest[]
     */
    private function loadMedicationRequests(int $encounterId): array
    {
        return MedicationRequest::find()
            ->andWhere(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    /**
     * @return ServiceRequest[]
     */
    private function loadServiceRequests(int $encounterId): array
    {
        return ServiceRequest::find()
            ->andWhere(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    /**
     * @return DiagnosticReport[]
     */
    private function loadDiagnosticReports(int $encounterId, int $personaId): array
    {
        return DiagnosticReport::find()
            ->andWhere([
                'or',
                ['encounter_id' => $encounterId],
                ['and', ['subject_persona_id' => $personaId], ['encounter_id' => null]],
            ])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['issued_at' => SORT_DESC])
            ->limit(10)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function textSection(string $title, string $text): array
    {
        return [
            'title' => $title,
            'text' => [
                'status' => 'generated',
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml">'
                    . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</div>',
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function patientIdentifiers(?Persona $persona): array
    {
        if ($persona === null) {
            return [];
        }
        $ids = [];
        if (!empty($persona->documento)) {
            $ids[] = [
                'system' => 'http://www.renaper.gob.ar/dni',
                'value' => (string) $persona->documento,
            ];
        }

        return $ids;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function patientName(?Persona $persona): array
    {
        if ($persona === null) {
            return [];
        }

        return [[
            'family' => (string) ($persona->apellido ?? ''),
            'given' => array_values(array_filter([(string) ($persona->nombre ?? '')])),
        ]];
    }

    private function mapGender(?Persona $persona): ?string
    {
        if ($persona === null || $persona->sexo_biologico === null) {
            return null;
        }
        $map = [1 => 'male', 2 => 'female', 3 => 'other'];

        return $map[(int) $persona->sexo_biologico] ?? 'unknown';
    }
}
