<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Workflow\EncounterCaptureActorCatalog;
use common\components\Domain\Clinical\Workflow\EncounterCaptureCategoryResolver;
use common\components\Domain\Clinical\Workflow\EncounterDefinitionWorkflowCatalog;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterDefinition;

class EncounterCaptureCategoryResolverTest extends Unit
{
    public function testMedicoImpNoAgregaSignosVitales(): void
    {
        $def = $this->definition(Encounter::ENCOUNTER_CLASS_IMP, EncounterDefinitionWorkflowCatalog::TEMPLATE_IMP_STANDARD);
        $cats = (new EncounterCaptureCategoryResolver())->resolve($def, [], EncounterCaptureActorCatalog::ACTOR_MEDICO);
        $modelos = array_column($cats, 'modelo');

        $this->assertNotContains('ConsultaAtencionesEnfermeria', $modelos);
        $this->assertContains('DiagnosticoConsulta', $modelos);
    }

    public function testEnfermeriaImpAgregaSignosVitalesSugeridos(): void
    {
        $def = $this->definition(Encounter::ENCOUNTER_CLASS_IMP, EncounterDefinitionWorkflowCatalog::TEMPLATE_IMP_STANDARD);
        $cats = (new EncounterCaptureCategoryResolver())->resolve(
            $def,
            [],
            EncounterCaptureActorCatalog::ACTOR_ENFERMERIA
        );
        $byModelo = [];
        foreach ($cats as $cat) {
            $byModelo[$cat['modelo']] = $cat;
        }

        $this->assertArrayHasKey('ConsultaAtencionesEnfermeria', $byModelo);
        $this->assertTrue($byModelo['ConsultaAtencionesEnfermeria']['sugerido']);
        $this->assertFalse($byModelo['ConsultaAtencionesEnfermeria']['requerido']);
        $this->assertTrue($byModelo['ConsultaBalanceHidrico']['sugerido']);
        $this->assertSame('Signos vitales', $cats[0]['titulo']);
    }

    public function testPlantillaNursingImpYaIncluyeSv(): void
    {
        $def = $this->definition(Encounter::ENCOUNTER_CLASS_IMP, EncounterDefinitionWorkflowCatalog::TEMPLATE_IMP_NURSING);
        $cats = (new EncounterCaptureCategoryResolver())->resolve(
            $def,
            [],
            EncounterCaptureActorCatalog::ACTOR_ENFERMERIA
        );
        $sv = array_values(array_filter(
            $cats,
            static fn ($c) => ($c['modelo'] ?? '') === 'ConsultaAtencionesEnfermeria'
        ));
        $this->assertCount(1, $sv);
    }

    public function testTemplateForServicioEnfermeria(): void
    {
        $servicio = new \common\models\Servicio();
        $servicio->item_name = 'enfermeria';
        $servicio->nombre = 'ENFERMERIA';

        $this->assertSame(
            EncounterDefinitionWorkflowCatalog::TEMPLATE_IMP_NURSING,
            EncounterDefinitionWorkflowCatalog::templateForServicio($servicio, Encounter::ENCOUNTER_CLASS_IMP)
        );
        $this->assertSame(
            EncounterDefinitionWorkflowCatalog::TEMPLATE_EMER_NURSING,
            EncounterDefinitionWorkflowCatalog::templateForServicio($servicio, Encounter::ENCOUNTER_CLASS_EMER)
        );
    }

    private function definition(string $class, string $template): EncounterDefinition
    {
        $def = new EncounterDefinition();
        $def->encounter_class = $class;
        $def->service_id = 1;
        $def->workflow_json = EncounterDefinitionWorkflowCatalog::workflowJsonForTemplate($template);

        return $def;
    }
}
