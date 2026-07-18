<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileCalculator;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileContract;
use common\models\Scheduling\PersonaTurnosPerfil;
use common\models\Scheduling\PersonaTurnosPerfilMetrica;
use common\models\TurnoEventoAudit;

class TurnoBehaviorProfileCalculatorTest extends Unit
{
    protected function _before(): void
    {
        TurnoBehaviorProfileContract::resetCacheForTests();
    }

    public function testWindowsAndScopesProduceDeterministicMetrics(): void
    {
        $calc = new TurnoBehaviorProfileCalculator(new TurnoBehaviorProfileContract([
            'version' => 1,
            'windows_days' => [90, 180],
            'scopes' => ['GLOBAL', 'EFECTOR'],
            'min_sample_size' => 2,
            'late_cancellation' => ['hours_before_appointment' => 24],
            'patient_attributed_actors' => ['PACIENTE', 'REPRESENTANTE'],
            'events' => [],
            'metrics' => [
                ['code' => 'CLOSED_ELIGIBLE', 'kind' => 'count'],
                ['code' => 'ATTENDED', 'kind' => 'count'],
                ['code' => 'NO_SHOW_ATTRIBUTABLE', 'kind' => 'count'],
                ['code' => 'NO_SHOW_RATE', 'kind' => 'rate', 'numerator' => 'NO_SHOW_ATTRIBUTABLE', 'denominator' => 'CLOSED_ELIGIBLE'],
                ['code' => 'CANCEL_PATIENT', 'kind' => 'count'],
                ['code' => 'CANCEL_LATE', 'kind' => 'count'],
            ],
        ]));

        $asOf = '2026-07-18 12:00:00';
        $events = [
            [
                'id' => 1,
                'id_turno' => 100,
                'event_code' => TurnoEventoAudit::EVENT_ATTENDED,
                'actor_type' => TurnoEventoAudit::ACTOR_STAFF,
                'attribution_quality' => TurnoEventoAudit::QUALITY_NATIVE,
                'occurred_at' => '2026-06-01 10:00:00',
                'cita_at' => '2026-06-01 10:00:00',
                'id_efector' => 7,
                'id_servicio' => 3,
                'modalidad' => 'presencial',
            ],
            [
                'id' => 2,
                'id_turno' => 101,
                'event_code' => TurnoEventoAudit::EVENT_NO_SHOW_RECORDED,
                'actor_type' => TurnoEventoAudit::ACTOR_PACIENTE,
                'attribution_quality' => TurnoEventoAudit::QUALITY_NATIVE,
                'occurred_at' => '2026-06-10 10:00:00',
                'cita_at' => '2026-06-10 10:00:00',
                'id_efector' => 7,
                'id_servicio' => 3,
                'modalidad' => 'presencial',
            ],
            [
                'id' => 3,
                'id_turno' => 102,
                'event_code' => TurnoEventoAudit::EVENT_APPOINTMENT_CANCELLED,
                'actor_type' => TurnoEventoAudit::ACTOR_PACIENTE,
                'attribution_quality' => TurnoEventoAudit::QUALITY_NATIVE,
                'occurred_at' => '2026-06-15 08:00:00',
                'cita_at' => '2026-06-15 09:00:00',
                'id_efector' => 7,
                'id_servicio' => 3,
                'modalidad' => 'teleconsulta',
            ],
            [
                'id' => 4,
                'id_turno' => 103,
                'event_code' => TurnoEventoAudit::EVENT_APPOINTMENT_CANCELLED,
                'actor_type' => TurnoEventoAudit::ACTOR_SISTEMA,
                'attribution_quality' => TurnoEventoAudit::QUALITY_NATIVE,
                'occurred_at' => '2026-06-16 08:00:00',
                'cita_at' => '2026-06-16 09:00:00',
                'id_efector' => 7,
                'id_servicio' => 3,
                'modalidad' => 'presencial',
            ],
        ];

        $a = $calc->calculate($events, $asOf);
        $b = $calc->calculate($events, $asOf);
        $this->assertSame($a, $b);
        $this->assertSame(PersonaTurnosPerfil::COMPLETENESS_COMPLETE, $a['completeness_status']);

        $global90 = $this->indexMetrics($a['metrics']);
        $this->assertSame(2, $global90['GLOBAL|null|90|CLOSED_ELIGIBLE']['numerator']);
        $this->assertSame(1, $global90['GLOBAL|null|90|ATTENDED']['numerator']);
        $this->assertSame(1, $global90['GLOBAL|null|90|NO_SHOW_ATTRIBUTABLE']['numerator']);
        $this->assertSame(1, $global90['GLOBAL|null|90|CANCEL_PATIENT']['numerator']);
        $this->assertSame(1, $global90['GLOBAL|null|90|CANCEL_LATE']['numerator']);
        // Sistema no cuenta como cancelación paciente.
        $this->assertSame(PersonaTurnosPerfilMetrica::CONFIDENCE_OK, $global90['GLOBAL|null|90|NO_SHOW_RATE']['confidence_status']);
        $this->assertEqualsWithDelta(0.5, (float) $global90['GLOBAL|null|90|NO_SHOW_RATE']['value'], 0.0001);

        $this->assertArrayHasKey('EFECTOR|7|90|CLOSED_ELIGIBLE', $global90);
    }

    public function testInsufficientDataAndZeroDenominator(): void
    {
        $calc = new TurnoBehaviorProfileCalculator(new TurnoBehaviorProfileContract([
            'version' => 1,
            'windows_days' => [90],
            'scopes' => ['GLOBAL'],
            'min_sample_size' => 5,
            'late_cancellation' => ['hours_before_appointment' => 24],
            'patient_attributed_actors' => ['PACIENTE'],
            'events' => [],
            'metrics' => [
                ['code' => 'CLOSED_ELIGIBLE', 'kind' => 'count'],
                ['code' => 'NO_SHOW_ATTRIBUTABLE', 'kind' => 'count'],
                ['code' => 'NO_SHOW_RATE', 'kind' => 'rate', 'numerator' => 'NO_SHOW_ATTRIBUTABLE', 'denominator' => 'CLOSED_ELIGIBLE'],
                ['code' => 'CONFIRMATION_RATE', 'kind' => 'rate', 'numerator' => 'CONFIRMATION_RESPONDED', 'denominator' => 'CONFIRMATION_DELIVERED'],
            ],
        ]));

        $result = $calc->calculate([
            [
                'id' => 1,
                'id_turno' => 1,
                'event_code' => TurnoEventoAudit::EVENT_ATTENDED,
                'actor_type' => TurnoEventoAudit::ACTOR_STAFF,
                'attribution_quality' => TurnoEventoAudit::QUALITY_NATIVE,
                'occurred_at' => '2026-07-01 10:00:00',
                'cita_at' => '2026-07-01 10:00:00',
            ],
        ], '2026-07-18 12:00:00');

        $this->assertSame(PersonaTurnosPerfil::COMPLETENESS_COMPLETE, $result['completeness_status']);
        $idx = $this->indexMetrics($result['metrics']);
        $this->assertSame(
            PersonaTurnosPerfilMetrica::CONFIDENCE_INSUFFICIENT_DATA,
            $idx['GLOBAL|null|90|NO_SHOW_RATE']['confidence_status']
        );
        $this->assertNull($idx['GLOBAL|null|90|NO_SHOW_RATE']['value']);
        $this->assertSame(
            PersonaTurnosPerfilMetrica::CONFIDENCE_NOT_APPLICABLE,
            $idx['GLOBAL|null|90|CONFIRMATION_RATE']['confidence_status']
        );
    }

    public function testNonNativeEventsAreIgnored(): void
    {
        $calc = new TurnoBehaviorProfileCalculator(new TurnoBehaviorProfileContract([
            'version' => 1,
            'windows_days' => [90],
            'scopes' => ['GLOBAL'],
            'min_sample_size' => 1,
            'late_cancellation' => ['hours_before_appointment' => 24],
            'patient_attributed_actors' => ['PACIENTE'],
            'events' => [],
            'metrics' => [
                ['code' => 'CLOSED_ELIGIBLE', 'kind' => 'count'],
                ['code' => 'ATTENDED', 'kind' => 'count'],
            ],
        ]));

        $result = $calc->calculate([
            [
                'id' => 1,
                'id_turno' => 1,
                'event_code' => TurnoEventoAudit::EVENT_ATTENDED,
                'actor_type' => TurnoEventoAudit::ACTOR_STAFF,
                'attribution_quality' => 'LEGACY_INFERRED',
                'occurred_at' => '2026-07-01 10:00:00',
                'cita_at' => '2026-07-01 10:00:00',
            ],
        ], '2026-07-18 12:00:00');

        $this->assertSame(PersonaTurnosPerfil::COMPLETENESS_EMPTY, $result['completeness_status']);
        $idx = $this->indexMetrics($result['metrics']);
        $this->assertSame(0, $idx['GLOBAL|null|90|ATTENDED']['numerator']);
    }

    public function testNoShowCorrectionAndStaffNoShowExcluded(): void
    {
        $calc = new TurnoBehaviorProfileCalculator(new TurnoBehaviorProfileContract([
            'version' => 1,
            'windows_days' => [90],
            'scopes' => ['GLOBAL'],
            'min_sample_size' => 1,
            'late_cancellation' => ['hours_before_appointment' => 24],
            'patient_attributed_actors' => ['PACIENTE'],
            'events' => [],
            'metrics' => [
                ['code' => 'CLOSED_ELIGIBLE', 'kind' => 'count'],
                ['code' => 'NO_SHOW_ATTRIBUTABLE', 'kind' => 'count'],
                ['code' => 'ATTENDED', 'kind' => 'count'],
            ],
        ]));

        $result = $calc->calculate([
            [
                'id' => 1,
                'id_turno' => 10,
                'event_code' => TurnoEventoAudit::EVENT_NO_SHOW_RECORDED,
                'actor_type' => TurnoEventoAudit::ACTOR_PACIENTE,
                'attribution_quality' => TurnoEventoAudit::QUALITY_NATIVE,
                'occurred_at' => '2026-07-01 10:00:00',
                'cita_at' => '2026-07-01 10:00:00',
            ],
            [
                'id' => 2,
                'id_turno' => 10,
                'event_code' => TurnoEventoAudit::EVENT_NO_SHOW_CORRECTED,
                'actor_type' => TurnoEventoAudit::ACTOR_STAFF,
                'attribution_quality' => TurnoEventoAudit::QUALITY_NATIVE,
                'occurred_at' => '2026-07-01 11:00:00',
                'cita_at' => '2026-07-01 10:00:00',
            ],
            [
                'id' => 3,
                'id_turno' => 10,
                'event_code' => TurnoEventoAudit::EVENT_ATTENDED,
                'actor_type' => TurnoEventoAudit::ACTOR_STAFF,
                'attribution_quality' => TurnoEventoAudit::QUALITY_NATIVE,
                'occurred_at' => '2026-07-01 12:00:00',
                'cita_at' => '2026-07-01 10:00:00',
            ],
            [
                'id' => 4,
                'id_turno' => 11,
                'event_code' => TurnoEventoAudit::EVENT_NO_SHOW_RECORDED,
                'actor_type' => TurnoEventoAudit::ACTOR_STAFF,
                'attribution_quality' => TurnoEventoAudit::QUALITY_NATIVE,
                'occurred_at' => '2026-07-02 10:00:00',
                'cita_at' => '2026-07-02 10:00:00',
            ],
        ], '2026-07-18 12:00:00');

        $idx = $this->indexMetrics($result['metrics']);
        $this->assertSame(1, $idx['GLOBAL|null|90|ATTENDED']['numerator']);
        $this->assertSame(0, $idx['GLOBAL|null|90|NO_SHOW_ATTRIBUTABLE']['numerator']);
        $this->assertSame(1, $idx['GLOBAL|null|90|CLOSED_ELIGIBLE']['numerator']);
    }

    /**
     * @param list<array<string, mixed>> $metrics
     * @return array<string, array<string, mixed>>
     */
    private function indexMetrics(array $metrics): array
    {
        $out = [];
        foreach ($metrics as $m) {
            $key = implode('|', [
                $m['scope_type'],
                $m['scope_id'] === null || $m['scope_id'] === '' ? 'null' : (string) $m['scope_id'],
                (string) $m['window_days'],
                $m['metric_code'],
            ]);
            $out[$key] = $m;
        }

        return $out;
    }
}
