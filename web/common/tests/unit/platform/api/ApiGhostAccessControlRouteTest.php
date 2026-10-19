<?php

namespace common\tests\unit\platform\api;

use Codeception\Test\Unit;
use frontend\modules\api\v1\components\BioenlaceApiAccessControl;

/**
 * Rutas RBAC vs uniqueId Yii (actionIndex).
 */
final class ApiGhostAccessControlRouteTest extends Unit
{
    public function testInfoIndexMapsToApiInfoPermission(): void
    {
        $candidates = BioenlaceApiAccessControl::permissionRouteCandidates('/api/info/index');

        $this->assertContains('/api/info/index', $candidates);
        $this->assertContains('/api/info', $candidates);
    }

    public function testListarIndexMapsToApiListarPermission(): void
    {
        $candidates = BioenlaceApiAccessControl::permissionRouteCandidates('/api/listar/index');

        $this->assertContains('/api/listar/index', $candidates);
        $this->assertContains('/api/listar', $candidates);
    }

    public function testAudioTranscriptionIsAvailableToAuthenticatedUsers(): void
    {
        $property = new \ReflectionProperty(BioenlaceApiAccessControl::class, 'authenticatedOnlyRoutes');
        $property->setAccessible(true);

        $this->assertContains('/api/audio/transcribir', $property->getValue());
    }
}
