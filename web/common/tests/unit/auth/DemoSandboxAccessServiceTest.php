<?php

namespace common\tests\unit\auth;

use Codeception\Test\Unit;
use common\components\Platform\Core\Auth\DemoSandboxAccessService;
use common\models\Platform\DemoSandboxAccess;
use Yii;

class DemoSandboxAccessServiceTest extends Unit
{
    public function testHashCodeIsStableSha256(): void
    {
        $plain = 'abc123';
        $this->assertSame(hash('sha256', $plain), DemoSandboxAccessService::hashCode($plain));
    }

    public function testRoleCatalog(): void
    {
        $this->assertContains(DemoSandboxAccess::ROLE_STAFF, DemoSandboxAccess::roleValues());
        $this->assertContains(DemoSandboxAccess::ROLE_PACIENTE, DemoSandboxAccess::roleValues());
    }

    public function testListProfilesRespectsParamsAccounts(): void
    {
        $prevEnabled = Yii::$app->params['demo_sandbox_habilitado'] ?? null;
        $prevCfg = Yii::$app->params['demo_sandbox'] ?? null;

        Yii::$app->params['demo_sandbox_habilitado'] = true;
        Yii::$app->params['demo_sandbox'] = [
            'accounts' => [
                'staff' => [
                    'username' => 'medico_med_general_863',
                    'label' => 'Médico demo',
                ],
                'paciente' => [
                    'username' => '',
                    'label' => 'Paciente',
                ],
            ],
        ];

        $profiles = DemoSandboxAccessService::listProfiles();
        $this->assertCount(1, $profiles);
        $this->assertSame('staff', $profiles[0]['role']);
        $this->assertSame('Médico demo', $profiles[0]['label']);

        Yii::$app->params['demo_sandbox_habilitado'] = $prevEnabled;
        Yii::$app->params['demo_sandbox'] = $prevCfg;
    }

    public function testIsEnabledFlag(): void
    {
        $prev = Yii::$app->params['demo_sandbox_habilitado'] ?? null;
        Yii::$app->params['demo_sandbox_habilitado'] = false;
        $this->assertFalse(DemoSandboxAccessService::isEnabled());
        Yii::$app->params['demo_sandbox_habilitado'] = true;
        $this->assertTrue(DemoSandboxAccessService::isEnabled());
        Yii::$app->params['demo_sandbox_habilitado'] = $prev;
    }
}
