<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Assistant\Catalog\IntentAliasCatalog;
use common\components\Assistant\IntentEngine\IntentClassificationRulesService;
use common\components\Assistant\IntentEngine\IntentClassifier;
use common\components\Assistant\IntentEngine\UiActionCatalogItem;

class IntentClassificationRulesServiceTest extends Unit
{
    protected function _after(): void
    {
        IntentClassificationRulesService::resetCacheForTests();
        IntentAliasCatalog::resetCacheForTests();
    }

    public function testStaffAgendaRuleMatchesFormasAtencion(): void
    {
        $this->assertTrue(IntentClassificationRulesService::ruleMatches(
            'staff_agenda_config_edit',
            'necesito modificar las formas de atencion de un profesional'
        ));
    }

    public function testScoreAdjustmentPrefersEditarOverLicencia(): void
    {
        $msg = 'necesito modificar las formas de atencion';
        $editar = new UiActionCatalogItem('data-access.editar', 'Edición dispersa', '', null, '/api/editar', ['modificar'], []);
        $licencia = new UiActionCatalogItem(
            'licencia.cargar-para-profesional-flow',
            'Licencia',
            '',
            null,
            '/api/test',
            ['licencia'],
            []
        );
        $lower = mb_strtolower($msg, 'UTF-8');
        $this->assertGreaterThan(
            IntentClassifier::scoreItemPublic($lower, $licencia),
            IntentClassifier::scoreItemPublic($lower, $editar)
        );
    }

    public function testAiPromptHintsDoNotContainHardcodedIntentIds(): void
    {
        foreach (IntentClassificationRulesService::aiPromptHintLines() as $line) {
            $this->assertStringNotContainsString('best_id =', $line);
            $this->assertDoesNotMatchRegularExpression('/\bdata-access\.\w+\b/', $line);
        }
    }
}
