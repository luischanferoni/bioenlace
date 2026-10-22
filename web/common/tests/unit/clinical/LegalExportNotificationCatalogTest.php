<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\LegalExport\Application\Catalog\LegalExportNotificationCatalog;

final class LegalExportNotificationCatalogTest extends Unit
{
    protected function _after(): void
    {
        LegalExportNotificationCatalog::resetCacheForTests();
    }

    public function testReturnsReadyNotificationCopy(): void
    {
        $this->assertSame(
            [
                'title' => 'Expediente legal listo',
                'body' => 'Ya podés descargar el expediente que solicitaste.',
            ],
            (new LegalExportNotificationCatalog())->ready()
        );
    }
}
