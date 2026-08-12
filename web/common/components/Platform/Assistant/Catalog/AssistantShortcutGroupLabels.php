<?php

namespace common\components\Platform\Assistant\Catalog;

/**
 * Etiquetas de grupos auto-generados por {@see AssistantShortcutsRbacGrouper}.
 *
 * Fuente: {@see AssistantShortcutGroupRegistry} (BD admin) → fallback YAML.
 */
final class AssistantShortcutGroupLabels
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return AssistantShortcutGroupRegistry::snapshot()['labels'];
    }

    /**
     * @return list<string>
     */
    public static function groupOrder(): array
    {
        return AssistantShortcutGroupRegistry::snapshot()['order'];
    }

    public static function resetCacheForTests(): void
    {
        AssistantShortcutGroupRegistry::resetCacheForTests();
    }
}
