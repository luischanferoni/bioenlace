<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\models\Clinical\Input\MedicacionInput;
use common\models\ConsultaMedicamentos;

class MedicacionInputTest extends Unit
{
    public function testMentionedOnlyNameIsValid(): void
    {
        $input = MedicacionInput::fromExtractedRow([
            'Nombre del medicamento' => 'Enalapril',
        ]);
        $this->assertSame(MedicacionInput::TYPE_MENTIONED, $input->tipo);
        $this->assertTrue($input->validate());
        $this->assertSame([], $input->missingFieldsForCompleteness());
    }

    public function testDosingInfersOrderedAndRequiresFrequency(): void
    {
        $input = MedicacionInput::fromExtractedRow([
            'Nombre del medicamento' => 'Enalapril',
            'Cantidad' => '10 mg',
        ]);
        $this->assertSame(MedicacionInput::TYPE_ORDERED, $input->tipo);
        $this->assertFalse($input->validate());
        $this->assertContains(
            MedicacionInput::FIELD_FRECUENCIA,
            $input->missingFieldsForCompleteness()
        );
    }

    public function testOrderedCompleteDefaultsTipoFrecuencia(): void
    {
        $input = MedicacionInput::fromExtractedRow([
            'Nombre del medicamento' => 'Enalapril',
            'Cantidad' => '10',
            'Frecuencia de administracion' => '1',
        ]);
        $this->assertSame(MedicacionInput::TYPE_ORDERED, $input->tipo);
        $this->assertSame(ConsultaMedicamentos::FRECUENCIA_TIPO_DIA, $input->tipoFrecuencia);
        $this->assertTrue($input->validate());
    }

    public function testPromptFieldsIncludeTipo(): void
    {
        $campos = (new ConsultaMedicamentos())->requeridosPrompt();
        $this->assertSame(MedicacionInput::FIELD_NOMBRE, $campos[0]);
        $this->assertContains(MedicacionInput::FIELD_TIPO, $campos);
    }
}
