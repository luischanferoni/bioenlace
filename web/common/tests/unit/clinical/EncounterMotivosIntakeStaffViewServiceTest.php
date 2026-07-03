<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterMotivosIntakeCatalogService;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterMotivosIntakeStaffViewService;
use common\models\Clinical\Encounter;

final class EncounterMotivosIntakeStaffViewServiceTest extends Unit
{
    protected function _after(): void
    {
        EncounterMotivosIntakeCatalogService::resetCacheForTests();
    }

    public function testReturnsNullWhenNoLegacyAnswers(): void
    {
        $encounter = new Encounter();
        $encounter->motivos_intake_json = null;

        $view = (new EncounterMotivosIntakeStaffViewService())->buildForEncounter($encounter);

        $this->assertNull($view);
    }

    public function testFormatsLegacySubmittedAnswersWithSelectLabels(): void
    {
        $encounter = new Encounter();
        $encounter->motivos_intake_json = json_encode([
            'motivo_principal' => 'control',
            'desde_cuando' => '3 días',
            'intensidad' => '3',
        ], JSON_UNESCAPED_UNICODE);

        $view = (new EncounterMotivosIntakeStaffViewService())->buildForEncounter($encounter);

        $this->assertIsArray($view);
        $this->assertSame('submitted', $view['status']);
        $this->assertNotEmpty($view['answers']);

        $byId = [];
        foreach ($view['answers'] as $row) {
            $byId[$row['id']] = $row;
        }

        $this->assertSame('control', $byId['motivo_principal']['answer']);
        $this->assertSame('3 días', $byId['desde_cuando']['answer']);
        $this->assertSame('3', $byId['intensidad']['answer']);
    }
}
