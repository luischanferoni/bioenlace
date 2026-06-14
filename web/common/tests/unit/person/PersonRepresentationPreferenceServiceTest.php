<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Representation\Service\PersonRepresentationPreferenceService;

class PersonRepresentationPreferenceServiceTest extends Unit
{
    public function testDefaultPreferenceIsFalse(): void
    {
        $service = new PersonRepresentationPreferenceService();

        $this->expectException(\InvalidArgumentException::class);
        $service->getForPersona(0);
    }

    public function testSaveRequiresFlag(): void
    {
        $service = new PersonRepresentationPreferenceService();

        $this->expectException(\InvalidArgumentException::class);
        $service->saveForPersona(1, []);
    }
}
