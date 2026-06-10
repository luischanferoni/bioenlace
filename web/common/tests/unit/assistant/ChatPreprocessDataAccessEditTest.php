<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Assistant\EntryPoints\Chat\Preprocess\ChatPreprocessService;

class ChatPreprocessDataAccessEditTest extends Unit
{
    public function testEditQueryDetected(): void
    {
        $this->assertTrue(ChatPreprocessService::isStaffDataAccessEditQuery('editar nombre del medico del centro'));
        $this->assertTrue(ChatPreprocessService::isStaffDataAccessEditQuery('modificar agenda del personal'));
        $this->assertTrue(ChatPreprocessService::isStaffDataAccessOperationalQuery('modificar agenda del personal'));
    }

    public function testListQueryIsNotEditQuery(): void
    {
        $this->assertFalse(ChatPreprocessService::isStaffDataAccessEditQuery('cuantos profesionales hay'));
        $this->assertTrue(ChatPreprocessService::isStaffDataAccessQuery('cuantos profesionales hay'));
        $this->assertTrue(ChatPreprocessService::isStaffDataAccessOperationalQuery('cuantos profesionales hay'));
    }

    public function testTurnoQueryExcludedFromEdit(): void
    {
        $this->assertFalse(ChatPreprocessService::isStaffDataAccessEditQuery('modificar turno del paciente'));
    }
}
