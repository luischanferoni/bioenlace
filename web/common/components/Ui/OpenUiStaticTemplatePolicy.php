<?php

namespace common\components\Ui;

use common\components\Assistant\Catalog\CarePackUiActionCatalog;
use common\components\Assistant\Catalog\ClinicalUiActionCatalog;
use common\components\Assistant\Catalog\DataAccessUiActionCatalog;
use common\components\Assistant\Catalog\PersonRepresentationUiActionCatalog;

/**
 * Qué pasos open_ui no requieren plantilla JSON estática bajo views/json/.
 */
final class OpenUiStaticTemplatePolicy
{
    /** @var array<string, true>|null */
    private static ?array $exemptActionIds = null;

    public static function requiresStaticTemplateFile(string $actionId): bool
    {
        $actionId = strtolower(trim($actionId));
        if ($actionId === '') {
            return true;
        }

        return !isset(self::loadExemptActionIds()[$actionId]);
    }

    /**
     * @return array<string, true>
     */
    private static function loadExemptActionIds(): array
    {
        if (self::$exemptActionIds !== null) {
            return self::$exemptActionIds;
        }

        $out = [];
        $catalogs = [
            ClinicalUiActionCatalog::class,
            PersonRepresentationUiActionCatalog::class,
            CarePackUiActionCatalog::class,
            DataAccessUiActionCatalog::class,
        ];
        foreach ($catalogs as $catalogClass) {
            if (!method_exists($catalogClass, 'discoverAll')) {
                continue;
            }
            foreach ($catalogClass::discoverAll() as $def) {
                if (!is_array($def)) {
                    continue;
                }
                $id = strtolower(trim((string) ($def['action_id'] ?? '')));
                if ($id === '') {
                    continue;
                }
                $clientOpen = $def['client_open'] ?? null;
                if (is_array($clientOpen) && ($clientOpen['kind'] ?? '') === 'native') {
                    $out[$id] = true;
                    continue;
                }
                if (!empty($def['ui_json_descriptor'])) {
                    $out[$id] = true;
                }
            }
        }

        self::$exemptActionIds = $out;

        return $out;
    }
}
