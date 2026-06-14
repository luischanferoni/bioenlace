<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\IntentEngine\IntentClassificationRulesService;
use common\components\Platform\Assistant\IntentEngine\IntentClassifier;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

class IntentClassificationRulesServiceTest extends Unit
{
    protected function _after(): void
    {
        IntentClassificationRulesService::resetCacheForTests();
    }

    public function testStaffAgendaRuleMatchesFormasAtencion(): void
    {
        $this->assertTrue(IntentClassificationRulesService::ruleMatches(
            'staff_agenda_config_edit',
            'necesito modificar las formas de atencion de un profesional'
        ));
    }

    public function testOperationalFallbackRoutesAgendaEditToEditar(): void
    {
        $catalog = \common\components\Platform\Assistant\IntentEngine\UiActionCatalog::fromItems(
            [
                new UiActionCatalogItem(
                    'data-access.editar',
                    'Edición dispersa',
                    '',
                    null,
                    '/api/editar',
                    ['modificar', 'modificar agenda'],
                    []
                ),
                new UiActionCatalogItem(
                    'profesional-efector-servicio.crear-flow',
                    'Alta',
                    '',
                    null,
                    '/api/test',
                    ['agenda'],
                    []
                ),
            ],
            []
        );
        $catalog->byActionId['data-access.editar'] = $catalog->items[0];
        $catalog->byActionId['profesional-efector-servicio.crear-flow'] = $catalog->items[1];

        $msg = 'necesito modificar la agenda de un profesional';
        $fb = IntentClassificationRulesService::resolveOperationalFallback($msg, $catalog);

        $this->assertNotNull($fb);
        $this->assertSame('data-access.editar', $fb['item']->action_id);
        $this->assertSame('rules_declarative_fallback', $fb['method']);
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
