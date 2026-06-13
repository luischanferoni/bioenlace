<?php

namespace common\tests\unit\core\Permission;

use common\components\Assistant\Catalog\IntentSchemaPaths;
use common\components\Core\Permission\IntentPermissionResolver;
use Codeception\Test\Unit;

class IntentPermissionResolverTest extends Unit
{
    public function testInferTurnoCreate(): void
    {
        $key = IntentPermissionResolver::inferFromIntentId(
            'turnos.crear-como-paciente',
            IntentSchemaPaths::CATEGORY_CREATE
        );
        $this->assertSame('Turno.create', $key);
    }

    public function testInferTurnoReprogramar(): void
    {
        $key = IntentPermissionResolver::inferFromIntentId(
            'turnos.modificar-como-paciente-flow',
            IntentSchemaPaths::CATEGORY_UPDATE
        );
        $this->assertSame('Turno.reprogramar', $key);
    }

    public function testExplicitPermissionWins(): void
    {
        $key = IntentPermissionResolver::resolve('turnos.crear-como-paciente', [
            'permission' => 'Custom.permission',
            'rbac_route' => '/api/turnos/crear-como-paciente',
        ]);
        $this->assertSame('Custom.permission', $key);
    }

    public function testInferInternacionMapaCamasFlow(): void
    {
        $key = IntentPermissionResolver::inferFromIntentId(
            'internacion.mapa-camas-flow',
            IntentSchemaPaths::CATEGORY_READ
        );
        $this->assertSame('Internacion.view_map', $key);
    }

    public function testInferDataAccessListar(): void
    {
        $key = IntentPermissionResolver::inferFromIntentId(
            'data-access.listar',
            IntentSchemaPaths::CATEGORY_READ
        );
        $this->assertSame('DataAccess.list', $key);
    }
}
