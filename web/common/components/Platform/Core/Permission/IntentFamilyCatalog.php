<?php

namespace common\components\Platform\Core\Permission;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Familias NL de intents (variantes propio/staff, etc.) declaradas en metadata.
 */
final class IntentFamilyCatalog
{
    /** @var array<string, array{operation: string, members: list<string>}>|null */
    private static ?array $families = null;

    /**
     * @return array<string, array{operation: string, members: list<string>}>
     */
    public static function all(): array
    {
        self::ensureLoaded();

        return self::$families ?? [];
    }

    /**
     * @return array{operation: string, members: list<string>}|null
     */
    public static function get(string $familyId): ?array
    {
        self::ensureLoaded();
        $familyId = trim($familyId);

        return self::$families[$familyId] ?? null;
    }

    public static function resetCache(): void
    {
        self::$families = null;
    }

    private static function ensureLoaded(): void
    {
        if (self::$families !== null) {
            return;
        }

        self::$families = [];
        $file = ProductMetadataPaths::intentFamiliesFile();
        if (!is_file($file)) {
            return;
        }

        try {
            $parsed = Yaml::parseFile($file);
        } catch (\Throwable $e) {
            return;
        }

        if (!is_array($parsed)) {
            return;
        }

        $families = $parsed['families'] ?? null;
        if (!is_array($families)) {
            return;
        }

        foreach ($families as $familyId => $def) {
            if (!is_string($familyId) || !is_array($def)) {
                continue;
            }
            $familyId = trim($familyId);
            if ($familyId === '') {
                continue;
            }

            $operation = trim((string) ($def['operation'] ?? ''));
            $membersRaw = $def['members'] ?? null;
            if ($operation === '' || !is_array($membersRaw)) {
                continue;
            }

            $members = [];
            foreach ($membersRaw as $member) {
                $memberId = trim((string) $member);
                if ($memberId !== '') {
                    $members[] = $memberId;
                }
            }
            if ($members === []) {
                continue;
            }

            self::$families[$familyId] = [
                'operation' => $operation,
                'members' => array_values(array_unique($members)),
            ];
        }
    }
}
