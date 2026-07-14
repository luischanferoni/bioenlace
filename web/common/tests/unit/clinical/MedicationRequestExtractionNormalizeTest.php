<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\MedicationRequestService;

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

    public function testNormalizeLegacyFieldName(): void
    {
        $rows = MedicationRequestService::normalizeExtractedMedicationPayload([
            [
                'Nombre del medicamento' => 'Enalapril',
                'Cantidad' => '10 mg',
                'Via de administracion' => 'vía oral',
                'Frecuencia de administracion' => '1',
                'Tipo de frecuencia' => 'DIA',
                'Duracion del tratamiento' => '30',
                'Tipo de duracion' => 'DIA',
            ],
        ]);
        $this->assertCount(1, $rows);
        $this->assertSame('Enalapril', MedicationRequestService::resolveMedicationDisplay($rows[0]));
        $this->assertSame('10 mg', $rows[0]['Cantidad']);
        $this->assertSame('vía oral', $rows[0]['Via de administracion']);
    }

    public function testEmptyPayload(): void
    {
        $this->assertSame([], MedicationRequestService::normalizeExtractedMedicationPayload(null));
        $this->assertSame([], MedicationRequestService::normalizeExtractedMedicationPayload([]));
    }
}
