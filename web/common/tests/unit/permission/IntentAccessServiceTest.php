<?php

namespace common\tests\unit\permission;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;
use common\components\Platform\Core\Permission\IntentAccessService;
use common\components\Platform\Core\Permission\IntentPermissionResolver;

/**
 * Listado de intents y ejecución comparten {@see IntentAccessService::userCanExecuteIntent}.
 * Atajos usan {@see IntentAccessService::userHasIntentGrant} (sin promoción por ruta).
 */
final class IntentAccessServiceTest extends Unit
{
    public function testPermissionKeyIsIntentId(): void
    {
        $this->assertSame(
            'tratamiento.adherencia-resumen-staff',
            IntentPermissionResolver::resolve('tratamiento.adherencia-resumen-staff')
        );
    }

    public function testFilterByRbacExcludesWhenUserHasNeitherIntentNorRoute(): void
    {
        $items = [
            [
                'action_id' => 'demo.intent',
                'rbac_route' => '/api/clinical/care-plan/active',
            ],
        ];

        $filtered = YamlIntentCatalogService::filterByRbac($items, 999_999);

        $this->assertSame([], $filtered);
    }

    public function testUserCannotExecuteWithoutUserId(): void
    {
        $this->assertFalse(IntentAccessService::userCanExecuteIntent(0, 'demo.intent'));
        $this->assertFalse(IntentAccessService::userCanExecuteIntent(-1, 'demo.intent'));
        $this->assertFalse(IntentAccessService::userCanExecuteIntent(1, ''));
    }

    public function testUserHasIntentGrantRequiresExplicitGrant(): void
    {
        $this->assertFalse(IntentAccessService::userHasIntentGrant(0, 'demo.intent'));
        $this->assertFalse(IntentAccessService::userHasIntentGrant(999_999, 'demo.intent'));
        $this->assertFalse(IntentAccessService::userHasIntentGrant(1, ''));
    }

    public function testFilterByIntentGrantExcludesWhenNoIntentGrant(): void
    {
        $intentId = 'urgencias.ver-tablero-guardia';
        if (!YamlIntentCatalogService::intentExists($intentId)) {
            $this->markTestSkipped('Intent fixture not present');
        }

        $items = [
            [
                'action_id' => $intentId,
                'rbac_route' => '/api/home/panel',
            ],
        ];

        $this->assertSame([], YamlIntentCatalogService::filterByIntentGrant($items, 999_999));
    }
}
