<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Prescription\Enum\PrescriptionLegalStatus;
use common\components\Domain\Clinical\Prescription\Service\PrescriptionRdiPreSubmitValidationService;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Clinical\ElectronicPrescription;
use common\models\Clinical\ElectronicPrescriptionItem;

class PrescriptionRdiPreSubmitValidationTest extends Unit
{
    protected function _before(): void
    {
        AutonomousAgentMetadata::resetCacheForTests();
    }

    public function testRdiIssueScenarioRequiresDiagnosisAndPes(): void
    {
        $rx = new ElectronicPrescription([
            'encounter_id' => 1,
            'subject_persona_id' => 1,
            'status' => PrescriptionLegalStatus::DRAFT,
        ]);
        $rx->scenario = ElectronicPrescription::SCENARIO_RDI_ISSUE;
        $this->assertFalse($rx->validate());
        $this->assertArrayHasKey('id_profesional_efector_servicio', $rx->getErrors());
        $this->assertArrayHasKey('diagnosis_code', $rx->getErrors());
    }

    public function testRdiIssueItemScenarioRequiresCodeAndDosage(): void
    {
        $item = new ElectronicPrescriptionItem([
            'electronic_prescription_id' => 1,
            'line_number' => 1,
            'medication_code' => '  ',
            'dosage_text' => '',
        ]);
        $item->scenario = ElectronicPrescriptionItem::SCENARIO_RDI_ISSUE;
        $this->assertFalse($item->validate());
        $this->assertArrayHasKey('medication_code', $item->getErrors());
        $this->assertArrayHasKey('dosage_text', $item->getErrors());
    }

    public function testValidateBlocksWithoutYamlGatesWhenIntegrityMissing(): void
    {
        $rx = new ElectronicPrescription([
            'encounter_id' => 1,
            'subject_persona_id' => 1,
            'status' => PrescriptionLegalStatus::DRAFT,
            'id_profesional_efector_servicio' => 10,
            'diagnosis_code' => '',
            'diagnosis_display' => 'ok',
        ]);
        $item = new ElectronicPrescriptionItem([
            'electronic_prescription_id' => 1,
            'line_number' => 1,
            'medication_code' => '',
            'dosage_text' => '1 comprimido cada 8 h',
        ]);
        $rx->populateRelation('items', [$item]);

        $errors = (new PrescriptionRdiPreSubmitValidationService())->validate($rx);
        $joined = implode(' ', $errors);
        $this->assertStringContainsString('diagnóstico codificado', $joined);
        $this->assertStringContainsString('código de medicamento', $joined);
    }

    public function testValidateOkWhenIntegrityPresent(): void
    {
        $rx = new ElectronicPrescription([
            'encounter_id' => 1,
            'subject_persona_id' => 1,
            'status' => PrescriptionLegalStatus::DRAFT,
            'id_profesional_efector_servicio' => 10,
            'diagnosis_code' => 'J06.9',
            'diagnosis_display' => 'Infección aguda vías respiratorias altas',
        ]);
        $item = new ElectronicPrescriptionItem([
            'electronic_prescription_id' => 1,
            'line_number' => 1,
            'medication_code' => '12345',
            'medication_display' => 'Paracetamol',
            'dosage_text' => '1 g cada 8 h',
        ]);
        $rx->populateRelation('items', [$item]);

        $errors = (new PrescriptionRdiPreSubmitValidationService())->validate($rx);
        $this->assertSame([], $errors);
    }

    public function testPolicyKnobsHaveDomainDefaults(): void
    {
        $knobs = (new PrescriptionRdiPreSubmitValidationService())->loadPolicyKnobs();
        $this->assertSame(24, $knobs['block_duplicate_medication_hours']);
        $this->assertSame(3, $knobs['min_diagnosis_display_length']);
    }
}
