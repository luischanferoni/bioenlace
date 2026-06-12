<?php

namespace common\tests\unit\core\DataAccess;

use Codeception\Test\Unit;
use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\DataAccess\DataAccessEditDiscoveryService;
use common\components\Core\DataAccess\PermissionContext;

class DataAccessEditDiscoveryTest extends Unit
{
    protected function _after(): void
    {
        AttributeGroupCatalog::resetCacheForTests();
    }

    public function testResolveSurfaceFromKeywords(): void
    {
        $svc = new DataAccessEditDiscoveryService();
        $ctx = new PermissionContext(1, ['AdminEfector']);
        $surface = $svc->resolveSurfaceId('editar personal del centro', [], $ctx);
        $this->assertSame('profesional_en_efector', $surface);
    }

    public function testResolveAspectAgendaUnambiguous(): void
    {
        $svc = new DataAccessEditDiscoveryService();
        $ctx = new PermissionContext(1, ['AdminEfector']);
        $aspects = $svc->resolveAspectIds(
            'modificar horarios de agenda del medico',
            'profesional_en_efector',
            [],
            $ctx
        );
        $this->assertSame(['agenda_horarios'], $aspects);
    }

    public function testResolveAspectFormasAtencion(): void
    {
        $svc = new DataAccessEditDiscoveryService();
        $ctx = new PermissionContext(1, ['AdminEfector']);
        $aspects = $svc->resolveAspectIds(
            'necesito modificar las formas de atencion de un profesional',
            'profesional_en_efector',
            [],
            $ctx
        );
        $this->assertSame(['agenda_horarios'], $aspects);
    }

    public function testAmbiguousAspectsReturnEmpty(): void
    {
        $svc = new DataAccessEditDiscoveryService();
        $ctx = new PermissionContext(1, ['AdminEfector']);
        $aspects = $svc->resolveAspectIds(
            'editar personal del centro',
            'profesional_en_efector',
            [],
            $ctx
        );
        $this->assertSame([], $aspects);
    }

    public function testResolveAspectIdentidad(): void
    {
        $svc = new DataAccessEditDiscoveryService();
        $ctx = new PermissionContext(1, ['AdminEfector']);
        $aspects = $svc->resolveAspectIds(
            'corregir apellido del profesional',
            'profesional_en_efector',
            [],
            $ctx
        );
        $this->assertSame(['identidad'], $aspects);
    }
}
