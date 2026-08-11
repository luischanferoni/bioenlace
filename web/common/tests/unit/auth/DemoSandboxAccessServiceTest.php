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
        $this->assertContains(DemoSandboxAccess::ROLE_ENFERMERIA, DemoSandboxAccess::roleValues());
        $this->assertContains(DemoSandboxAccess::ROLE_ADMINISTRATIVO, DemoSandboxAccess::roleValues());
        $this->assertContains(DemoSandboxAccess::ROLE_PACIENTE, DemoSandboxAccess::roleValues());
        $this->assertTrue(DemoSandboxAccess::isEphemeralStaffRole(DemoSandboxAccess::ROLE_ENFERMERIA));
        $this->assertTrue(DemoSandboxAccess::isEphemeralStaffRole(DemoSandboxAccess::ROLE_ADMINISTRATIVO));
        $this->assertSame('administrativo', DemoSandboxAccess::staffKindForRole(DemoSandboxAccess::ROLE_ADMINISTRATIVO));
        $this->assertSame('demo_a_', DemoSandboxAccess::usernamePrefixForStaffKind('administrativo'));
        $this->assertSame('EMER', DemoSandboxAccess::defaultEncounterClassForRole(DemoSandboxAccess::ROLE_ADMINISTRATIVO));
        $this->assertSame('AMB', DemoSandboxAccess::defaultEncounterClassForRole(DemoSandboxAccess::ROLE_STAFF));
    }

    public function testListProfilesEphemeralFromProfilesConfig(): void
    {
        $prevEnabled = Yii::$app->params['demo_sandbox_habilitado'] ?? null;
        $prevCfg = Yii::$app->params['demo_sandbox'] ?? null;

        Yii::$app->params['demo_sandbox_habilitado'] = true;
        Yii::$app->params['demo_sandbox'] = [
            'profiles' => [
                'staff' => [
                    'label' => 'Médico demo',
                    'mode' => 'ephemeral',
                ],
            ],
        ];

        $profiles = DemoSandboxAccessService::listProfiles();
        $this->assertCount(1, $profiles);
        $this->assertSame('staff', $profiles[0]['role']);
        $this->assertSame('Médico demo', $profiles[0]['label']);
        $this->assertSame(DemoSandboxAccessService::MODE_EPHEMERAL, $profiles[0]['mode']);

        Yii::$app->params['demo_sandbox_habilitado'] = $prevEnabled;
        Yii::$app->params['demo_sandbox'] = $prevCfg;
    }

    public function testListProfilesLegacyAccountsStillForcesStaffEphemeral(): void
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
        // Staff nunca se ofrece como shared_account (legacy), aunque exista en accounts.
        $this->assertSame(DemoSandboxAccessService::MODE_EPHEMERAL, $profiles[0]['mode']);

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
