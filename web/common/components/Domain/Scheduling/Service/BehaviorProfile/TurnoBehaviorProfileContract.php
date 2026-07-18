<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use common\models\TurnoEventoAudit;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Contrato versionado de eventos y métricas (metadata declarativa).
 */
final class TurnoBehaviorProfileContract
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /** @var array<string, mixed> */
    private array $data;

    public function __construct(?array $data = null)
    {
        $this->data = $data ?? self::load();
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    public function version(): int
    {
        return (int) ($this->data['version'] ?? 1);
    }

    public function contractId(): string
    {
        return (string) ($this->data['contract_id'] ?? 'turno-behavior-profile');
    }

    /** @return list<int> */
    public function windowsDays(): array
    {
        $w = $this->data['windows_days'] ?? [90, 180, 365];
        if (!is_array($w) || $w === []) {
            return [90, 180, 365];
        }

        return array_values(array_map('intval', $w));
    }

    /** @return list<string> */
    public function scopes(): array
    {
        $s = $this->data['scopes'] ?? ['GLOBAL', 'EFECTOR', 'SERVICIO', 'MODALIDAD'];
        if (!is_array($s) || $s === []) {
            return ['GLOBAL', 'EFECTOR', 'SERVICIO', 'MODALIDAD'];
        }

        return array_values(array_map('strval', $s));
    }

    public function minSampleSize(): int
    {
        return max(1, (int) ($this->data['min_sample_size'] ?? 5));
    }

    public function lateCancellationHours(): int
    {
        $late = $this->data['late_cancellation'] ?? [];
        if (!is_array($late)) {
            return 24;
        }

        return max(0, (int) ($late['hours_before_appointment'] ?? 24));
    }

    /** @return list<string> */
    public function patientAttributedActors(): array
    {
        $a = $this->data['patient_attributed_actors'] ?? [
            TurnoEventoAudit::ACTOR_PACIENTE,
            TurnoEventoAudit::ACTOR_REPRESENTANTE,
        ];
        if (!is_array($a) || $a === []) {
            return [TurnoEventoAudit::ACTOR_PACIENTE, TurnoEventoAudit::ACTOR_REPRESENTANTE];
        }

        return array_values(array_map('strval', $a));
    }

    /** @return list<string> */
    public function eventCodes(): array
    {
        $events = $this->data['events'] ?? [];
        if (!is_array($events) || $events === []) {
            return [];
        }

        return array_values(array_map('strval', array_keys($events)));
    }

    /**
     * @return list<array{code: string, kind: string, numerator?: string, denominator?: string}>
     */
    public function metrics(): array
    {
        $raw = $this->data['metrics'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $m) {
            if (!is_array($m) || empty($m['code'])) {
                continue;
            }
            $out[] = [
                'code' => (string) $m['code'],
                'kind' => (string) ($m['kind'] ?? 'count'),
                'numerator' => isset($m['numerator']) ? (string) $m['numerator'] : null,
                'denominator' => isset($m['denominator']) ? (string) $m['denominator'] : null,
            ];
        }

        return $out;
    }

    public function eventCodeForLegacyTipo(string $legacyTipo): ?string
    {
        $events = $this->data['events'] ?? [];
        if (!is_array($events)) {
            return null;
        }
        foreach ($events as $code => $def) {
            $legacy = is_array($def) ? ($def['legacy_tipos'] ?? []) : [];
            if (is_array($legacy) && in_array($legacyTipo, $legacy, true)) {
                return (string) $code;
            }
        }

        return null;
    }

    public function legacyTipoForEvent(string $eventCode): ?string
    {
        $def = $this->data['events'][$eventCode] ?? null;
        if (!is_array($def)) {
            return null;
        }
        $legacy = $def['legacy_tipos'] ?? [];
        if (!is_array($legacy) || $legacy === []) {
            return null;
        }

        return (string) $legacy[0];
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = ProductMetadataPaths::turnoBehaviorProfileContractFile();
        if (!is_file($path)) {
            if (class_exists(\Yii::class, false)) {
                Yii::warning('TurnoBehaviorProfileContract: falta ' . $path, __METHOD__);
            }
            self::$cache = self::fallback();

            return self::$cache;
        }

        try {
            $parsed = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            if (class_exists(\Yii::class, false)) {
                Yii::warning('TurnoBehaviorProfileContract: YAML inválido: ' . $e->getMessage(), __METHOD__);
            }
            self::$cache = self::fallback();

            return self::$cache;
        }

        self::$cache = is_array($parsed) ? $parsed : self::fallback();

        return self::$cache;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fallback(): array
    {
        return [
            'version' => 1,
            'contract_id' => 'turno-behavior-profile',
            'windows_days' => [90, 180, 365],
            'scopes' => ['GLOBAL', 'EFECTOR', 'SERVICIO', 'MODALIDAD'],
            'min_sample_size' => 5,
            'late_cancellation' => ['hours_before_appointment' => 24],
            'patient_attributed_actors' => [
                TurnoEventoAudit::ACTOR_PACIENTE,
                TurnoEventoAudit::ACTOR_REPRESENTANTE,
            ],
            'events' => [
                TurnoEventoAudit::EVENT_APPOINTMENT_CREATED => ['legacy_tipos' => [TurnoEventoAudit::TIPO_CREATE]],
                TurnoEventoAudit::EVENT_APPOINTMENT_CANCELLED => [
                    'legacy_tipos' => [
                        TurnoEventoAudit::TIPO_CANCEL_PAC,
                        TurnoEventoAudit::TIPO_CANCEL_MED,
                        TurnoEventoAudit::TIPO_BULK_DAY_CANCEL,
                    ],
                ],
                TurnoEventoAudit::EVENT_CONFIRMED => ['legacy_tipos' => [TurnoEventoAudit::TIPO_CONFIRMED]],
                TurnoEventoAudit::EVENT_ATTENDED => ['legacy_tipos' => []],
                TurnoEventoAudit::EVENT_NO_SHOW_RECORDED => ['legacy_tipos' => [TurnoEventoAudit::TIPO_NO_SHOW]],
                TurnoEventoAudit::EVENT_NO_SHOW_CORRECTED => ['legacy_tipos' => []],
                TurnoEventoAudit::EVENT_APPOINTMENT_RESCHEDULED => ['legacy_tipos' => []],
                TurnoEventoAudit::EVENT_CONFIRMATION_REQUESTED => ['legacy_tipos' => []],
                TurnoEventoAudit::EVENT_CONFIRMATION_DELIVERY_CONFIRMED => ['legacy_tipos' => []],
                TurnoEventoAudit::EVENT_SYSTEM_SLOT_RELEASED => ['legacy_tipos' => []],
            ],
            'metrics' => [
                ['code' => 'CLOSED_ELIGIBLE', 'kind' => 'count'],
                ['code' => 'ATTENDED', 'kind' => 'count'],
                ['code' => 'NO_SHOW_ATTRIBUTABLE', 'kind' => 'count'],
                ['code' => 'NO_SHOW_RATE', 'kind' => 'rate', 'numerator' => 'NO_SHOW_ATTRIBUTABLE', 'denominator' => 'CLOSED_ELIGIBLE'],
                ['code' => 'CANCEL_PATIENT', 'kind' => 'count'],
                ['code' => 'CANCEL_EARLY', 'kind' => 'count'],
                ['code' => 'CANCEL_LATE', 'kind' => 'count'],
                ['code' => 'RESCHEDULED', 'kind' => 'count'],
                ['code' => 'CONFIRMATION_REQUESTED', 'kind' => 'count'],
                ['code' => 'CONFIRMATION_DELIVERED', 'kind' => 'count'],
                ['code' => 'CONFIRMATION_RESPONDED', 'kind' => 'count'],
                ['code' => 'CONFIRMATION_RATE', 'kind' => 'rate', 'numerator' => 'CONFIRMATION_RESPONDED', 'denominator' => 'CONFIRMATION_DELIVERED'],
                ['code' => 'ATTENDED_AFTER_CONFIRM', 'kind' => 'count'],
                ['code' => 'CONFIRMED_CLOSED', 'kind' => 'count'],
                ['code' => 'ATTENDED_AFTER_CONFIRM_RATE', 'kind' => 'rate', 'numerator' => 'ATTENDED_AFTER_CONFIRM', 'denominator' => 'CONFIRMED_CLOSED'],
                ['code' => 'COVERAGE_NATIVE', 'kind' => 'count'],
                ['code' => 'COVERAGE_INFERRED', 'kind' => 'count'],
                ['code' => 'COVERAGE_RATE', 'kind' => 'rate', 'numerator' => 'COVERAGE_NATIVE', 'denominator' => 'CLOSED_ELIGIBLE'],
            ],
        ];
    }
}
