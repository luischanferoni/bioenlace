<?php

namespace common\tests\unit\ai;

use Codeception\Test\Unit;
use common\components\Platform\Ai\Providers\Google\GoogleCloudConfigResolver;
use Yii;

class GoogleCloudConfigResolverTest extends Unit
{
    public function testResolvesRelativeCredentialsPathFromCommonConfig(): void
    {
        $fixture = sys_get_temp_dir() . '/bioenlace-gcp-test-' . uniqid() . '.json';
        file_put_contents($fixture, json_encode([
            'type' => 'service_account',
            'project_id' => 'demo-project',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nTEST\n-----END PRIVATE KEY-----\n",
            'client_email' => 'demo@demo.iam.gserviceaccount.com',
        ], JSON_UNESCAPED_UNICODE));

        $previous = Yii::$app->params;
        Yii::$app->params = array_merge($previous, [
            'google_cloud_credentials_path' => $fixture,
            'google_cloud_project_id' => '',
        ]);

        try {
            $this->assertSame(realpath($fixture), GoogleCloudConfigResolver::credentialsPath());
            $this->assertSame('demo-project', GoogleCloudConfigResolver::projectId());
        } finally {
            Yii::$app->params = $previous;
            @unlink($fixture);
        }
    }
}
