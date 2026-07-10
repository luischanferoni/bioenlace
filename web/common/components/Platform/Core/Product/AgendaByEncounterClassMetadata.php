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
    public static function coberturaClasses(): array
    {
        $out = [];
        foreach (self::loadConfig()['kinds'] ?? [] as $code => $kind) {
            if (!is_array($kind)) {
                continue;
            }
            if (($kind['storage'] ?? '') === 'cobertura_interval') {
                $out[] = (string) $code;
            }
        }

        return $out !== [] ? $out : [Encounter::ENCOUNTER_CLASS_EMER, Encounter::ENCOUNTER_CLASS_IMP];
    }

    public static function isCoberturaClass(string $encounterClass): bool
    {
        return in_array($encounterClass, self::coberturaClasses(), true);
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

    public static function coberturaOverlapSamePersonaEfector(): bool
    {
        $conflicts = self::loadConfig()['conflicts'] ?? [];

        return (bool) ($conflicts['cobertura_overlap_same_persona_efector'] ?? true);
    }
}
