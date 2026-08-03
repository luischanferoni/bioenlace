<?php

namespace common\tests\unit\auth;

use Codeception\Test\Unit;
use common\components\Platform\Core\Auth\DemoSandboxCaptchaService;
use Yii;

class DemoSandboxCaptchaServiceTest extends Unit
{
    public function testIssueReturnsChallengeAndImage(): void
    {
        $prevEnabled = Yii::$app->params['demo_sandbox_habilitado'] ?? null;
        $prevCfg = Yii::$app->params['demo_sandbox'] ?? null;

        Yii::$app->params['demo_sandbox_habilitado'] = true;
        Yii::$app->params['demo_sandbox'] = [
            'require_captcha' => true,
            'captcha_ttl_seconds' => 120,
            'captcha_length' => 4,
        ];

        try {
            $svc = new DemoSandboxCaptchaService();
            $this->assertTrue($svc->isRequired());
            $issued = $svc->issue();
            $this->assertNotSame('', $issued['challenge_id']);
            $this->assertStringStartsWith('data:image/', $issued['image_data_url']);
            $this->assertGreaterThanOrEqual(60, $issued['expires_in']);
        } finally {
            Yii::$app->params['demo_sandbox_habilitado'] = $prevEnabled;
            Yii::$app->params['demo_sandbox'] = $prevCfg;
        }
    }

    public function testAssertValidConsumesChallenge(): void
    {
        $prevEnabled = Yii::$app->params['demo_sandbox_habilitado'] ?? null;
        $prevCfg = Yii::$app->params['demo_sandbox'] ?? null;

        Yii::$app->params['demo_sandbox_habilitado'] = true;
        Yii::$app->params['demo_sandbox'] = [
            'require_captcha' => true,
            'captcha_ttl_seconds' => 120,
            'captcha_length' => 4,
        ];

        try {
            $svc = new DemoSandboxCaptchaService();
            $issued = $svc->issue();
            $id = $issued['challenge_id'];

            $code = 'TEST';
            Yii::$app->cache->set('demo_sandbox_captcha:' . $id, hash('sha256', $code), 120);
            $svc->assertValid($id, 'test');

            $threw = false;
            try {
                $svc->assertValid($id, 'test');
            } catch (\DomainException $e) {
                $threw = true;
                $this->assertStringContainsString('Captcha', $e->getMessage());
            }
            $this->assertTrue($threw);
        } finally {
            Yii::$app->params['demo_sandbox_habilitado'] = $prevEnabled;
            Yii::$app->params['demo_sandbox'] = $prevCfg;
        }
    }

    public function testNotRequiredSkipsValidation(): void
    {
        $prevEnabled = Yii::$app->params['demo_sandbox_habilitado'] ?? null;
        $prevCfg = Yii::$app->params['demo_sandbox'] ?? null;

        Yii::$app->params['demo_sandbox_habilitado'] = true;
        Yii::$app->params['demo_sandbox'] = ['require_captcha' => false];

        try {
            $svc = new DemoSandboxCaptchaService();
            $this->assertFalse($svc->isRequired());
            $svc->assertValid(null, null);
        } finally {
            Yii::$app->params['demo_sandbox_habilitado'] = $prevEnabled;
            Yii::$app->params['demo_sandbox'] = $prevCfg;
        }
    }

    public function testWrongCodeFails(): void
    {
        $prevEnabled = Yii::$app->params['demo_sandbox_habilitado'] ?? null;
        $prevCfg = Yii::$app->params['demo_sandbox'] ?? null;

        Yii::$app->params['demo_sandbox_habilitado'] = true;
        Yii::$app->params['demo_sandbox'] = [
            'require_captcha' => true,
            'captcha_ttl_seconds' => 120,
        ];

        try {
            $svc = new DemoSandboxCaptchaService();
            $issued = $svc->issue();
            Yii::$app->cache->set(
                'demo_sandbox_captcha:' . $issued['challenge_id'],
                hash('sha256', 'ABCD'),
                120
            );
            $threw = false;
            try {
                $svc->assertValid($issued['challenge_id'], 'ZZZZ');
            } catch (\DomainException $e) {
                $threw = true;
            }
            $this->assertTrue($threw);
        } finally {
            Yii::$app->params['demo_sandbox_habilitado'] = $prevEnabled;
            Yii::$app->params['demo_sandbox'] = $prevCfg;
        }
    }
}
