<?php

namespace common\tests\unit\permission;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;
use common\components\Platform\Core\Permission\IntentAccessService;
use common\components\Platform\Core\Permission\IntentPermissionResolver;

/**
 * Listado de intents y ejecución comparten {@see IntentAccessService}.
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

    public function testFilterByRbacUsesIntentAccessOnly(): void
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
}
