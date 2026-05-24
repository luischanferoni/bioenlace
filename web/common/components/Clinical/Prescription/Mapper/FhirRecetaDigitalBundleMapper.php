<?php

namespace common\components\Clinical\Prescription\Mapper;

use common\models\Clinical\ElectronicPrescription;
use common\models\Person\Persona;

/**
 * Snapshot Bundle al perfil MSAL RDI (sin envío al repositorio — Fase 1).
 */
final class FhirRecetaDigitalBundleMapper
{
    private const PROFILE = 'http://fhir.msal.gob.ar/RDI/StructureDefinition/recetaDigitalRegistroRecetaAR';
    private const PROBLEMAS_SALUD_CS = 'http://fhir.msal.gob.ar/RDI/CodeSystem/csproblemas-salud';

    public function toBundleArray(ElectronicPrescription $rx, ?Persona $patient = null): array
    {
        $rx->refresh();
        $items = $rx->items;

        $medicationRequests = [];
        $line = 0;
        foreach ($items as $item) {
            $line++;
            $medicationRequests[] = [
                'resourceType' => 'MedicationRequest',
                'id' => 'rx-item-' . $line,
                'status' => 'active',
                'intent' => 'order',
                'medicationCodeableConcept' => [
                    'coding' => array_values(array_filter([
                        $item->medication_code ? [
                            'system' => $item->medication_code_system ?? 'http://snomed.info/sct',
                            'code' => $item->medication_code,
                            'display' => $item->medication_display,
                        ] : null,
                    ])),
                    'text' => $item->medication_display,
                ],
                'subject' => [
                    'reference' => 'Patient/' . $rx->subject_persona_id,
                    'display' => $patient ? trim($patient->nombre . ' ' . $patient->apellido) : null,
                ],
                'authoredOn' => $rx->issued_at ?? date('c'),
                'dosageInstruction' => $item->dosage_text ? [['text' => $item->dosage_text]] : [],
            ];
        }

        $entries = [];
        foreach ($medicationRequests as $mr) {
            $entries[] = [
                'fullUrl' => 'urn:uuid:' . ($mr['id'] ?? uniqid('mr-', true)),
                'resource' => $mr,
            ];
        }

        return [
            'resourceType' => 'Bundle',
            'type' => 'transaction',
            'timestamp' => date('c'),
            'meta' => ['profile' => [self::PROFILE]],
            'identifier' => [
                'system' => 'https://bioenlace.local/electronic-prescription',
                'value' => $rx->prescription_number ?? ('draft-' . $rx->id),
            ],
            'entry' => $entries,
            'extension' => array_filter([
                $rx->diagnosis_code ? [
                    'url' => 'diagnosis',
                    'valueCodeableConcept' => [
                        'coding' => [[
                            'system' => $rx->diagnosis_code_system ?? self::PROBLEMAS_SALUD_CS,
                            'code' => $rx->diagnosis_code,
                            'display' => $rx->diagnosis_display,
                        ]],
                    ],
                ] : null,
            ]),
        ];
    }
}
