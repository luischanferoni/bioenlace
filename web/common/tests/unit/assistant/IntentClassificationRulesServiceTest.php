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

    public function testOperationalFallbackRoutesAgendaEditToConfigurarStaff(): void
    {
        $catalog = \common\components\Platform\Assistant\IntentEngine\UiActionCatalog::fromItems(
            [
                new UiActionCatalogItem(
                    'profesional-agenda.configurar-staff',
                    'Configurar agenda (staff)',
                    '',
                    null,
                    '/api/profesional-agenda/configurar-agenda',
                    ['modificar agenda de un profesional'],
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
        $catalog->byActionId['profesional-agenda.configurar-staff'] = $catalog->items[0];
        $catalog->byActionId['profesional-efector-servicio.crear-flow'] = $catalog->items[1];

        $msg = 'necesito modificar la agenda de un profesional';
        $fb = IntentClassificationRulesService::resolveOperationalFallback($msg, $catalog);

        $this->assertNotNull($fb);
        $this->assertSame('profesional-agenda.configurar-staff', $fb['item']->action_id);
        $this->assertSame('rules_declarative_fallback', $fb['method']);
    }

    public function testScoreAdjustmentPrefersConfigurarStaffOverLicencia(): void
    {
        $msg = 'necesito modificar las formas de atencion';
        $configurar = new UiActionCatalogItem(
            'profesional-agenda.configurar-staff',
            'Configurar agenda',
            '',
            null,
            '/api/profesional-agenda/configurar-agenda',
            ['formas de atencion'],
            []
        );
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
            IntentClassifier::scoreItemPublic($lower, $configurar)
        );
    }

    public function testAiPromptHintsDoNotContainHardcodedIntentIds(): void
    {
        foreach (IntentClassificationRulesService::aiPromptHintLines() as $line) {
            $this->assertStringNotContainsString('best_id =', $line);
            $this->assertDoesNotMatchRegularExpression('/\bdata-access\.\w+\b/', $line);
        }
    }

    public function testClinicalSymptomRuleMatches(): void
    {
        $this->assertTrue(IntentClassificationRulesService::isClinicalSymptomContent('me duele la cabeza'));
        $this->assertFalse(IntentClassificationRulesService::isClinicalSymptomContent('quiero un turno'));
    }

    public function testStaffDataAccessEditExcludesScheduling(): void
    {
        $this->assertTrue(IntentClassificationRulesService::isStaffDataAccessEditQuery('modificar agenda del personal'));
        $this->assertFalse(IntentClassificationRulesService::isStaffDataAccessEditQuery('modificar turno del paciente'));
    }
}
