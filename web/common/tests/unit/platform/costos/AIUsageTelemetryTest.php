<?php

namespace common\tests\unit\platform\costos;

use common\components\Platform\Ai\Cost\AIUsageAggregator;
use common\components\Platform\Ai\Cost\AIUsageRecorder;
use Yii;

class AIUsageTelemetryTest extends \Codeception\Test\Unit
{
    /** @var array<string, mixed> */
    private array $paramsBackup = [];
    private ?string $tmpDir = null;

    protected function _before()
    {
        $this->paramsBackup = Yii::$app->params;
        $this->tmpDir = sys_get_temp_dir() . '/bioenlace-ai-usage-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpDir, 0775, true);
        Yii::$app->params['ai_usage_persist_habilitado'] = true;
        Yii::$app->params['ai_usage_log_dir'] = $this->tmpDir;
    }

    protected function _after()
    {
        Yii::$app->params = $this->paramsBackup;
        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tmpDir);
        }
    }

    public function testEstimateCostSplitsBillableCachedAndOutput(): void
    {
        $usage = ['prompt' => 1000, 'cached' => 250, 'output' => 500, 'thoughts' => 0];
        // 750 input × 0,10/M + 250 cached × 0,01/M + 500 output × 0,40/M
        $this->assertEqualsWithDelta(0.0002775, AIUsageRecorder::estimateCost($usage), 1e-9);
    }

    public function testRecorderAppendsOneJsonLineWithoutPromptOrResponse(): void
    {
        $response = json_encode([
            'usageMetadata' => [
                'promptTokenCount' => 1000,
                'cachedContentTokenCount' => 250,
                'candidatesTokenCount' => 500,
                'thoughtsTokenCount' => 0,
            ],
        ]);

        AIUsageRecorder::record(
            'analisis-consulta',
            'google',
            'gemini-2.5-flash-lite',
            'analysis',
            (string) $response,
            120,
            true,
            ['id_efector' => 7, 'encounter_id' => 99],
            hash('sha256', 'texto clínico confidencial')
        );

        $path = AIUsageRecorder::logPath();
        $this->assertFileExists($path);
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(1, $lines);

        $line = json_decode((string) $lines[0], true);
        $this->assertSame('analisis-consulta', $line['ctx']);
        $this->assertSame(1000, $line['prompt']);
        $this->assertSame(250, $line['cached']);
        $this->assertSame(500, $line['output']);
        $this->assertSame(7, $line['efector']);
        $this->assertSame(99, $line['enc']);
        $this->assertTrue($line['ok']);
        $this->assertEqualsWithDelta(0.0002775, $line['usd'], 1e-9);
        $this->assertArrayNotHasKey('prompt_text', $line);
        $this->assertStringNotContainsString('confidencial', (string) $lines[0]);
    }

    public function testAggregateFileGroupsByContextAndEfector(): void
    {
        $path = $this->tmpDir . '/ai-usage-2026-11-12.jsonl';
        $lines = [
            ['ctx' => 'chat', 'efector' => 1, 'prompt' => 100, 'cached' => 0, 'output' => 50, 'usd' => 0.1, 'ms' => 100, 'ok' => true],
            ['ctx' => 'chat', 'efector' => 1, 'prompt' => 200, 'cached' => 100, 'output' => 60, 'usd' => 0.2, 'ms' => 200, 'ok' => false],
            ['ctx' => 'captura', 'efector' => 2, 'prompt' => 300, 'cached' => 0, 'output' => 70, 'usd' => 0.3, 'ms' => 300, 'ok' => true],
        ];
        foreach ($lines as $line) {
            file_put_contents($path, json_encode($line) . PHP_EOL, FILE_APPEND);
        }

        $result = AIUsageAggregator::aggregateFile($path, '2026-11-12');

        $this->assertSame(3, $result['lines']);
        $this->assertCount(2, $result['rows']);

        $chat = $result['rows']['chat|1'];
        $this->assertSame(2, $chat['calls']);
        $this->assertSame(1, $chat['errors']);
        $this->assertSame(300, $chat['prompt_tokens']);
        $this->assertSame(100, $chat['cached_tokens']);
        $this->assertSame(110, $chat['output_tokens']);
        $this->assertSame(300, $chat['latency_ms_sum']);
        $this->assertEqualsWithDelta(0.3, $chat['cost_usd'], 1e-9);

        $captura = $result['rows']['captura|2'];
        $this->assertSame(1, $captura['calls']);
        $this->assertSame(0, $captura['errors']);
    }

    public function testRecorderDoesNothingWhenDisabled(): void
    {
        Yii::$app->params['ai_usage_persist_habilitado'] = false;

        AIUsageRecorder::record('chat', 'google', 'gemini-2.5-flash-lite', 'analysis', '{}', 10, true);

        $this->assertFileDoesNotExist(AIUsageRecorder::logPath());
    }
}
