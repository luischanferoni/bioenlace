<?php

namespace common\components\Platform\Assistant\WhatsApp;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Alcance del canal WhatsApp: intents permitidos y si son escrituras.
 *
 * Default bloqueado. La lista vive en `Channels/WhatsApp/channel-scope.yaml`;
 * los intents clínicos no se declaran.
 */
final class WhatsAppChannelScopeCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function isAllowed(string $intentId): bool
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return false;
        }

        return isset(self::intents()[$intentId]);
    }

    public static function isMutating(string $intentId): bool
    {
        $row = self::intents()[trim($intentId)] ?? null;

        return is_array($row) && !empty($row['mutating']);
    }

    /**
     * @return array<string, array{mutating: bool}>
     */
    public static function intents(): array
    {
        $rows = self::loadConfig()['intents'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $id => $row) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $out[$id] = [
                'mutating' => is_array($row) ? !empty($row['mutating']) : false,
            ];
        }

        return $out;
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
        AssistantMetadataLoader::resetCacheForTests();
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $data = AssistantMetadataLoader::load(
            ProductMetadataPaths::assistantChannelScopeFile('WhatsApp')
        );
        self::$config = is_array($data) ? $data : [];

        return self::$config;
    }
}
