<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\AssistantShortcutsCatalog;

class AssistantShortcutsStaffRolesTest extends Unit
{
    protected function _after(): void
    {
        AssistantShortcutsCatalog::resetCacheForTests();
    }

    public function testMatchesStaffAudienceExcludeWinsOverInclude(): void
    {
        $def = [
            'staff_roles' => ['Administrativo', 'Medico'],
            'exclude_staff_roles' => ['Administrativo'],
        ];
        $this->assertFalse(AssistantShortcutsCatalog::matchesStaffAudience($def, ['Administrativo']));
        $this->assertTrue(AssistantShortcutsCatalog::matchesStaffAudience($def, ['Medico']));
    }

    public function testMatchesIntentRuleByPrefixAndExclude(): void
    {
        $cat = [
            'intent_prefixes' => ['urgencias.'],
            'exclude_intent_ids' => ['urgencias.internal-only'],
        ];
        $this->assertTrue(AssistantShortcutsCatalog::matchesIntentRule('urgencias.ver-tablero-guardia', $cat));
        $this->assertFalse(AssistantShortcutsCatalog::matchesIntentRule('urgencias.internal-only', $cat));
        $this->assertFalse(AssistantShortcutsCatalog::matchesIntentRule('internacion.ingreso-flow', $cat));
    }

    public function testResolveSubgroupBySuffixAndDefault(): void
    {
        $cat = [
            'subgroups' => [
                [
                    'id' => 'own',
                    'intent_suffixes' => ['.editar-propio'],
                ],
                [
                    'id' => 'team',
                    'default' => true,
                ],
            ],
        ];
        $this->assertSame('own', AssistantShortcutsCatalog::resolveSubgroupForIntent('condicion-laboral.editar-propio', $cat));
        $this->assertSame('team', AssistantShortcutsCatalog::resolveSubgroupForIntent('profesional-efector-servicio.crear-flow', $cat));
    }
}
