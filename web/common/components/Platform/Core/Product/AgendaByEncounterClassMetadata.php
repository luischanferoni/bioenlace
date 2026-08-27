<?php

namespace common\components\Platform\Core\Product;

use common\models\Clinical\Encounter;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata de agendas tipadas por encounter_class ({@see ProductMetadataPaths::agendaByEncounterClassFile()}).
 */
final class AgendaByEncounterClassMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function reset(): void
    {
        self::$config = null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $path = ProductMetadataPaths::agendaByEncounterClassFile();
        if (!is_file($path)) {
            Yii::warning('Falta metadata agenda-by-encounter-class: ' . $path, __METHOD__);
            self::$config = [];

            return self::$config;
        }

        $data = Yaml::parseFile($path);
        self::$config = is_array($data) ? $data : [];

        return self::$config;
    }

    /**
     * @return list<string>
     */
    public static function horarioIntervalClasses(): array
    {
        $out = [];
        foreach (self::loadConfig()['kinds'] ?? [] as $code => $kind) {
            if (!is_array($kind)) {
                continue;
            }
            if (($kind['storage'] ?? '') === 'horario_interval') {
                $out[] = (string) $code;
            }
        }

        return $out !== [] ? $out : [Encounter::ENCOUNTER_CLASS_EMER, Encounter::ENCOUNTER_CLASS_IMP];
    }

    public static function isHorarioIntervalClass(string $encounterClass): bool
    {
        return in_array($encounterClass, self::horarioIntervalClasses(), true);
    }

    public static function isPatientBookingClass(string $encounterClass): bool
    {
        $kind = self::loadConfig()['kinds'][$encounterClass] ?? null;
        if (!is_array($kind)) {
            return $encounterClass === Encounter::ENCOUNTER_CLASS_AMB;
        }

        return (bool) ($kind['patient_booking'] ?? false);
    }

    /**
     * @return list<string>
     */
    public static function patientSlotFinderClasses(): array
    {
        $list = self::loadConfig()['patient_exposure']['slot_finder_encounter_classes'] ?? null;
        if (!is_array($list) || $list === []) {
            return [Encounter::ENCOUNTER_CLASS_AMB];
        }

        $out = [];
        foreach ($list as $code) {
            if (is_string($code) && $code !== '') {
                $out[] = $code;
            }
        }

        return $out !== [] ? $out : [Encounter::ENCOUNTER_CLASS_AMB];
    }

    public static function horarioOverlapSamePersonaEfector(): bool
    {
        $conflicts = self::loadConfig()['conflicts'] ?? [];

        return (bool) ($conflicts['horario_overlap_same_persona_efector'] ?? true);
    }

    public static function horarioVsAmbSlots(): bool
    {
        $conflicts = self::loadConfig()['conflicts'] ?? [];

        return (bool) ($conflicts['horario_vs_amb_slots'] ?? true);
    }

    public static function emerAssignRequiresHorario(): bool
    {
        $ops = self::loadConfig()['operational'] ?? [];

        return (bool) ($ops['emer_assign_requires_horario'] ?? true);
    }

    public static function emerAssignAllowWithoutAnyPresence(): bool
    {
        $ops = self::loadConfig()['operational'] ?? [];

        return (bool) ($ops['emer_assign_allow_without_any_presence'] ?? false);
    }

    /** Ver listado de internados (piso) exige horario IMP vigente. */
    public static function impViewRequiresHorario(): bool
    {
        $ops = self::loadConfig()['operational'] ?? [];

        return (bool) ($ops['imp_view_requires_horario'] ?? true);
    }
}
