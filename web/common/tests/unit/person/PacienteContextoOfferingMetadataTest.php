<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\PacienteContextoOfferingMetadata;
use common\models\Person\PersonaPacienteContexto;

class PacienteContextoOfferingMetadataTest extends Unit
{
    protected function _before(): void
    {
        PacienteContextoOfferingMetadata::resetCacheForTests();
    }

    public function testSectorPublicoIncluyeSoloPublico(): void
    {
        $rules = PacienteContextoOfferingMetadata::origenFinanciamientoRulesForSector(
            PersonaPacienteContexto::SECTOR_SALUD_PUBLICO
        );

        $this->assertContains('Privado', $rules['exclude']);
        $this->assertContains('Público', $rules['include']);
        $this->assertNotContains('Provincial', $rules['include']);
        $this->assertNotContains('Nacional', $rules['include']);
    }

    public function testSectorPrivadoSoloIncluyePrivado(): void
    {
        $rules = PacienteContextoOfferingMetadata::origenFinanciamientoRulesForSector(
            PersonaPacienteContexto::SECTOR_SALUD_PRIVADO
        );

        $this->assertSame(['Privado'], $rules['include']);
        $this->assertSame([], $rules['exclude']);
    }

    public function testIntentsRequierenContextoOperativoIncluyenTurnosPaciente(): void
    {
        $ids = PacienteContextoOfferingMetadata::intentIdsRequiringOperativeContext();

        $this->assertContains('turnos.crear-como-paciente', $ids);
        $this->assertContains('atencion.necesito-atencion', $ids);
        $this->assertNotContains('atencion.mis-atenciones-como-paciente', $ids);
        $this->assertNotContains('paciente-contexto.recurso-provincial-como-paciente-flow', $ids);
    }

    public function testHomePanelOcultaSeccionesSinContextoListado(): void
    {
        $sections = PacienteContextoOfferingMetadata::homePanelSectionsRequiringOperativeContext();

        $this->assertContains('upcoming_appointments', $sections);
        $this->assertContains('patient_async_consultations', $sections);
    }
}
