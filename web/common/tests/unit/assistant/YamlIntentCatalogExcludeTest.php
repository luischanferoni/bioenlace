<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;

class YamlIntentCatalogExcludeTest extends Unit
{
    public function testCatalogOnlyDataAccessIntentsNotInYamlCatalog(): void
    {
        $this->assertFalse(YamlIntentCatalogService::intentExists('data-access.info'));
        $this->assertFalse(YamlIntentCatalogService::intentExists('data-access.listar'));
        $this->assertFalse(YamlIntentCatalogService::intentExists('data-access.editar'));
    }

    public function testRemovedAgendaEditFlowsDoNotExist(): void
    {
        $this->assertFalse(YamlIntentCatalogService::intentExists('profesional-agenda.editar-flow'));
        $this->assertFalse(YamlIntentCatalogService::intentExists('profesional-agenda.editar-mi-flow'));
        $this->assertFalse(YamlIntentCatalogService::intentExists('agenda.editar-agenda-flow'));
        $this->assertFalse(YamlIntentCatalogService::intentExists('agenda.editar-mi-agenda-flow'));
    }
}
