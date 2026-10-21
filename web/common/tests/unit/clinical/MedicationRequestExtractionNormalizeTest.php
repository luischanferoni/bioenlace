<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\CarePlan\Application\UseCase\ManageMedicationRequest;
use common\models\Clinical\EncounterMedication;

class MedicationRequestExtractionNormalizeTest extends Unit
{
    public function testNormalizeStringPayload(): void
    {
        $rows = ManageMedicationRequest::normalizeExtractedMedicationPayload(
            'enalapril 10 mg por vía oral una vez al día durante 30 días'
        );
        $this->assertCount(1, $rows);
        $this->assertSame(
            'enalapril 10 mg por vía oral una vez al día durante 30 días',
            ManageMedicationRequest::resolveMedicationDisplay($rows[0])
        );
    }

    public function testNormalizeAssociativeObjectPayload(): void
    {
        $rows = ManageMedicationRequest::normalizeExtractedMedicationPayload([
            'texto' => 'enalapril 10 mg VO c/24h x 30 días',
        ]);
        $this->assertCount(1, $rows);
        $this->assertSame(
            'enalapril 10 mg VO c/24h x 30 días',
            ManageMedicationRequest::resolveMedicationDisplay($rows[0])
        );
    }

    public function testNormalizeUsingCamposPromptExtraccion(): void
    {
        $campos = EncounterMedication::camposPromptExtraccion();
        $this->assertNotEmpty($campos);

        $valores = [
            'Enalapril',
            '10 mg',
            'vía oral',
            '1',
            EncounterMedication::FRECUENCIA_TIPO_DIA,
            '30',
            EncounterMedication::DURANTE_TIPO_DIA,
        ];
        $this->assertCount(count($campos), $valores, 'Fixture alineado a camposPromptExtraccion()');

        $row = array_combine($campos, $valores);
        $this->assertIsArray($row);

        $rows = ManageMedicationRequest::normalizeExtractedMedicationPayload([$row]);
        $this->assertCount(1, $rows);
        $this->assertSame($valores[0], ManageMedicationRequest::resolveMedicationDisplay($rows[0]));
        $this->assertSame($valores[1], $rows[0][$campos[1]]);
        $this->assertSame($valores[2], $rows[0][$campos[2]]);
    }

    public function testEmptyPayload(): void
    {
        $this->assertSame([], ManageMedicationRequest::normalizeExtractedMedicationPayload(null));
        $this->assertSame([], ManageMedicationRequest::normalizeExtractedMedicationPayload([]));
    }
}
