<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\IntentShortcutMetadata;

class IntentShortcutMetadataTest extends Unit
{
    protected function _before(): void
    {
        \common\components\Platform\Core\Permission\IntentManifestIndex::resetCache();
    }

    public function testTableroGuardiaOpensNativeUi(): void
    {
        $this->assertTrue(IntentShortcutMetadata::opensNativeUi('urgencias.ver-tablero-guardia'));
    }

    public function testTriageGuardiaDoesNotOpenNativeUi(): void
    {
        $this->assertFalse(IntentShortcutMetadata::opensNativeUi('urgencias.triage-paciente-guardia'));
    }

    public function testBlankIntentDoesNotOpenNativeUi(): void
    {
        $this->assertFalse(IntentShortcutMetadata::opensNativeUi(''));
    }
}
