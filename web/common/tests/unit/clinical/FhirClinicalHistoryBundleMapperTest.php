<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\components\Domain\Integrations\ClinicalHistory\Mapper\FhirClinicalHistoryBundleMapper;
use common\models\Clinical\Encounter;

class FhirClinicalHistoryBundleMapperTest extends \Codeception\Test\Unit
{
    public function testBundleMatchesGoldenStructure(): void
    {
        $encounter = new Encounter([
            'id' => 42,
            'subject_persona_id' => 7,
            'encounter_class' => 'AMB',
            'status' => EncounterStatus::FINISHED,
            'period_start' => '2026-06-18 10:00:00',
            'period_end' => '2026-06-18 10:30:00',
            'note' => 'Evolución de prueba.',
            'reason_text' => 'Control anual',
        ]);

        $bundle = (new FhirClinicalHistoryBundleMapper())->buildForEncounter($encounter);
        $goldenPath = codecept_data_dir('fhir/encounter-document-v1-minimal.structure.json');
        verify(file_exists($goldenPath))->true();

        $golden = json_decode((string) file_get_contents($goldenPath), true);
        verify($golden)->array();

        foreach ($golden['required_top_level'] as $key) {
            verify($bundle)->hasKey($key);
        }

        verify($bundle['resourceType'])->equals($golden['resourceType']);
        verify($bundle['type'])->equals($golden['type']);
        verify($bundle['identifier']['value'])->equals('bioenlace-encounter-42');

        $types = array_map(
            static fn (array $e) => $e['resource']['resourceType'] ?? '',
            $bundle['entry']
        );

        foreach ($golden['required_resource_types'] as $resourceType) {
            verify($types)->contains($resourceType);
        }

        $composition = null;
        foreach ($bundle['entry'] as $entry) {
            if (($entry['resource']['resourceType'] ?? '') === 'Composition') {
                $composition = $entry['resource'];
                break;
            }
        }
        verify($composition)->notNull();
        foreach ($golden['composition_required'] as $key) {
            verify($composition)->hasKey($key);
        }
        verify($composition['subject']['reference'])->equals('Patient/7');
        verify($composition['encounter']['reference'])->equals('Encounter/42');
    }
}
