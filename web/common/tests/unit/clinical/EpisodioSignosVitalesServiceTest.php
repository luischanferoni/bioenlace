<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\EpisodioSignosVitalesService;
use ReflectionClass;

class EpisodioSignosVitalesServiceTest extends Unit
{
    public function testExtractFromDatosParsesCanonicalKeys(): void
    {
        $svc = new EpisodioSignosVitalesService();
        $ref = new ReflectionClass($svc);
        $method = $ref->getMethod('extractFromDatos');
        $method->setAccessible(true);

        /** @var array<string, float> $out */
        $out = $method->invoke($svc, [
            'TensionArterial1' => ['271649006' => 120, '271650006' => 80],
            '364075005' => 88,
            '86290005' => 18,
            '103228002' => 97,
            'temperatura' => 37.2,
            'glucemia_capilar' => 110,
            'glasgow' => 15,
        ]);

        $this->assertSame(120.0, $out[EpisodioSignosVitalesService::METRIC_TA_SYS]);
        $this->assertSame(80.0, $out[EpisodioSignosVitalesService::METRIC_TA_DIA]);
        $this->assertSame(88.0, $out[EpisodioSignosVitalesService::METRIC_FC]);
        $this->assertSame(18.0, $out[EpisodioSignosVitalesService::METRIC_FR]);
        $this->assertSame(97.0, $out[EpisodioSignosVitalesService::METRIC_SAT]);
        $this->assertSame(37.2, $out[EpisodioSignosVitalesService::METRIC_TEMP]);
        $this->assertSame(110.0, $out[EpisodioSignosVitalesService::METRIC_GLUC]);
        $this->assertSame(15.0, $out[EpisodioSignosVitalesService::METRIC_GLASGOW]);
    }
}
