<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\IntentFieldResolutionService;
use common\components\Platform\Core\Permission\IntentManifestIndex;

class IntentFieldResolutionTest extends Unit
{
    protected function _before(): void
    {
        IntentManifestIndex::resetCache();
    }

    public function testMatchFieldKeywordsFromPilotIntent(): void
    {
        $resolver = new IntentFieldResolutionService();
        $matched = $resolver->matchFieldNames(
            'condicion-laboral.editar-propio',
            'necesito cambiar la fecha de inicio de mi licencia'
        );

        $this->assertContains('fecha_inicio', $matched);
    }

    public function testRejectKeywordsDetectUnavailableField(): void
    {
        $resolver = new IntentFieldResolutionService();
        $this->assertTrue($resolver->mentionsUnavailableField(
            'condicion-laboral.editar-propio',
            'quiero cambiar el apellido de mi licencia'
        ));
    }

    public function testUnavailableMessageListsFieldGroups(): void
    {
        $msg = (new IntentFieldResolutionService())->unavailableFieldMessage('condicion-laboral.editar-propio');
        $this->assertStringContainsString('no está disponible', $msg);
        $this->assertStringContainsString('Vigencia', $msg);
    }
}
