<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Encounter\Application\Service\EpisodioSignosVitalesService;
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

    public function testMetricMetadataDefinesDisplayLabelsAndUnits(): void
    {
        $service = new EpisodioSignosVitalesService();
        $method = (new \ReflectionClass($service))->getMethod('metricMeta');
        $method->setAccessible(true);

        $this->assertSame([
            'ta_sys' => ['label' => 'TA sistólica', 'unit' => 'mmHg', 'intent' => 'danger', 'format' => 'integer'],
            'ta_dia' => ['label' => 'TA diastólica', 'unit' => 'mmHg', 'intent' => 'warning', 'format' => 'integer'],
            'fc' => ['label' => 'Frecuencia cardíaca', 'unit' => 'lpm', 'intent' => 'primary', 'format' => 'integer'],
            'fr' => ['label' => 'Frecuencia respiratoria', 'unit' => 'rpm', 'intent' => 'info', 'format' => 'integer'],
            'sat_o2' => ['label' => 'Saturación O₂', 'unit' => '%', 'intent' => 'info', 'format' => 'integer'],
            'temp' => ['label' => 'Temperatura', 'unit' => '°C', 'intent' => 'warning', 'format' => 'decimal'],
            'glucemia' => ['label' => 'Glucemia capilar', 'unit' => 'mg/dL', 'intent' => 'success', 'format' => 'decimal'],
            'glasgow' => ['label' => 'Glasgow', 'unit' => 'pts', 'intent' => 'secondary', 'format' => 'integer'],
        ], $method->invoke($service));
    }

    public function testMetricMetadataIsAvailableWhenEpisodeHasNoMeasurements(): void
    {
        $service = (new \ReflectionClass(EpisodioSignosVitalesService::class))
            ->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass($service))->getMethod('finalize');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'GUARDIA', 45, []);

        $this->assertSame([], $result['series']);
        $this->assertSame([], $result['ultimos']);
        $this->assertSame(0, $result['total_points']);
        $this->assertSame('vital_signs', $result['sections'][0]['key']);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], array_column($result['sections'][0]['fields'], 'order'));
        $this->assertSame(
            ['ta_sys', 'ta_dia', 'fc', 'fr', 'sat_o2', 'temp', 'glucemia', 'glasgow'],
            array_column($result['sections'][0]['fields'], 'key')
        );
        $this->assertSame(
            ['integer', 'integer', 'integer', 'integer', 'integer', 'decimal', 'decimal', 'integer'],
            array_column($result['sections'][0]['fields'], 'format')
        );
        $this->assertNull($result['sections'][0]['fields'][0]['value']);
        $this->assertSame(
            'TA sistólica',
            $result['metric_metadata'][EpisodioSignosVitalesService::METRIC_TA_SYS]['label']
        );
        $this->assertSame(
            'mmHg',
            $result['metric_metadata'][EpisodioSignosVitalesService::METRIC_TA_SYS]['unit']
        );
    }

    public function testFinalizePublishesLatestVitalsAsGenericFieldsAndKeepsLegacySeries(): void
    {
        $service = (new \ReflectionClass(EpisodioSignosVitalesService::class))
            ->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass($service))->getMethod('finalize');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'INTERNACION', 45, [
            [
                'metric' => EpisodioSignosVitalesService::METRIC_TA_SYS,
                'at' => '2026-10-23 07:30:00',
                'value' => 120.0,
                'source' => 'nursing',
                'ref_id' => 'nursing:1',
            ],
        ]);

        $this->assertSame(120.0, $result['sections'][0]['fields'][0]['value']);
        $this->assertSame('mmHg', $result['sections'][0]['fields'][0]['unit']);
        $this->assertSame('danger', $result['sections'][0]['fields'][0]['intent']);
        $this->assertSame('integer', $result['sections'][0]['fields'][0]['format']);
        $this->assertSame('ta_sys', $result['series'][0]['metric']);
        $this->assertSame(120.0, $result['ultimos']['ta_sys']['value']);
        $this->assertSame(1, $result['total_points']);
    }
}
