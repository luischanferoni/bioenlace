<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\MedicationRequestService;
use common\models\ConsultaMedicamentos;

class MedicationRequestExtractionNormalizeTest extends Unit
{
    public function testNormalizeStringPayload(): void
    {
        $rows = MedicationRequestService::normalizeExtractedMedicationPayload(
            'enalapril 10 mg por vía oral una vez al día durante 30 días'
        );
        $this->assertCount(1, $rows);
        $this->assertSame(
            'enalapril 10 mg por vía oral una vez al día durante 30 días',
            MedicationRequestService::resolveMedicationDisplay($rows[0])
        );
    }

    public function testNormalizeAssociativeObjectPayload(): void
    {
        $rows = MedicationRequestService::normalizeExtractedMedicationPayload([
            'texto' => 'enalapril 10 mg VO c/24h x 30 días',
        ]);
        $this->assertCount(1, $rows);
        $this->assertSame(
            'enalapril 10 mg VO c/24h x 30 días',
            MedicationRequestService::resolveMedicationDisplay($rows[0])
        );
    }

    public function testNormalizeUsingCamposPromptExtraccion(): void
    {
        $campos = ConsultaMedicamentos::camposPromptExtraccion();
        $this->assertNotEmpty($campos);

        $valores = [
            'Enalapril',
            '10 mg',
            'vía oral',
            '1',
            ConsultaMedicamentos::FRECUENCIA_TIPO_DIA,
            '30',
            ConsultaMedicamentos::DURANTE_TIPO_DIA,
        ];
        $this->assertCount(count($campos), $valores, 'Fixture alineado a camposPromptExtraccion()');

        $row = array_combine($campos, $valores);
        $this->assertIsArray($row);

        $rows = MedicationRequestService::normalizeExtractedMedicationPayload([$row]);
        $this->assertCount(1, $rows);
        $this->assertSame($valores[0], MedicationRequestService::resolveMedicationDisplay($rows[0]));
        $this->assertSame($valores[1], $rows[0][$campos[1]]);
        $this->assertSame($valores[2], $rows[0][$campos[2]]);
    }

    public function testEmptyPayload(): void
    {
        $this->assertSame([], MedicationRequestService::normalizeExtractedMedicationPayload(null));
        $this->assertSame([], MedicationRequestService::normalizeExtractedMedicationPayload([]));
    }
}
