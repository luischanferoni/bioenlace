<?php

namespace common\components\Platform\Assistant\Context;

/**
 * Aspectos de contexto HIS (volcado 2ª IA). Clave JSON = {@see aspectKey()}.
 */
final class AssistantContextHISAreaAspect
{
    public const APPOINTMENT_CURRENT = 'appointment.current';
    public const SITE_APPOINTMENT_POLICIES = 'site.appointment.policies';
    public const APPOINTMENT_SCHEDULING_SETUP = 'appointment.scheduling.setup';
    public const APPOINTMENT_HISTORY_SUBJECT_AT_SITE = 'appointment.history.subject_at_site';

    /** @var array<string, array{area: string, priority: int, implemented: bool}> */
    private const META = [
        self::APPOINTMENT_CURRENT => [
            'area' => AssistantContextHISArea::APPOINTMENTS,
            'priority' => 10,
            'implemented' => true,
        ],
        self::SITE_APPOINTMENT_POLICIES => [
            'area' => AssistantContextHISArea::APPOINTMENTS,
            'priority' => 30,
            'implemented' => true,
        ],
        self::APPOINTMENT_SCHEDULING_SETUP => [
            'area' => AssistantContextHISArea::APPOINTMENTS,
            'priority' => 25,
            'implemented' => true,
        ],
        self::APPOINTMENT_HISTORY_SUBJECT_AT_SITE => [
            'area' => AssistantContextHISArea::APPOINTMENTS,
            'priority' => 40,
            'implemented' => true,
        ],
    ];

    public static function aspectKey(string $aspect): string
    {
        return $aspect;
    }

    public static function isValid(string $aspect): bool
    {
        return isset(self::META[trim($aspect)]);
    }

    public static function isImplemented(string $aspect): bool
    {
        return (self::META[$aspect]['implemented'] ?? false) === true;
    }

    public static function area(string $aspect): string
    {
        return (string) (self::META[$aspect]['area'] ?? '');
    }

    public static function priority(string $aspect): int
    {
        return (int) (self::META[$aspect]['priority'] ?? 100);
    }

    /**
     * @return list<string>
     */
    public static function allForArea(string $areaId): array
    {
        $out = [];
        foreach (self::META as $aspect => $meta) {
            if (($meta['area'] ?? '') === $areaId) {
                $out[] = $aspect;
            }
        }

        sort($out);

        return $out;
    }
}
