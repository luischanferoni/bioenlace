<?php

namespace common\tests\unit\agent;

use common\components\Domain\Scheduling\Service\ReservaTriagePostCupoRoutingService;
use common\tests\unit\DbTestCase;

class ReservaTriagePostCupoRoutingServiceTest extends DbTestCase
{
    public function testRecommendsAsyncForCronicoSinCupo(): void
    {
        $svc = new ReservaTriagePostCupoRoutingService();
        $facts = [
            'urgency_band' => 'C',
            'reserva_triage_code' => 'control_cronico',
            'async_available' => 'true',
            'tele_hub_available' => 'false',
            'primaria_available' => 'false',
            'especialista_sin_cupo' => 'false',
            'slots_empty' => true,
        ];

        $rec = $svc->resolveRecommendation($facts);

        $this->assertNotNull($rec);
        $this->assertSame('recommend', $rec['action']);
        $this->assertSame('async', $rec['channel']);
        $this->assertSame('cronico_async', $rec['rule_id']);
    }

    public function testHaltsOnBandA(): void
    {
        $svc = new ReservaTriagePostCupoRoutingService();
        $facts = [
            'urgency_band' => 'A',
            'reserva_triage_code' => 'dolor_pecho',
            'async_available' => 'true',
            'slots_empty' => true,
        ];

        $rec = $svc->resolveRecommendation($facts);

        $this->assertNotNull($rec);
        $this->assertSame('halt', $rec['action']);
        $this->assertSame('banda_a_halt', $rec['rule_id']);
    }
}
