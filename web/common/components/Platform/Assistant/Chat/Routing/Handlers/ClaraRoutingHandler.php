<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Platform\Assistant\Catalog\YamlIntentManifestLoader;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Context\AssistantContextAssemblyService;
use common\components\Platform\Assistant\Copy\AssistantChannelCopy;
use common\components\Platform\Assistant\Copy\IntentMatchLaunchCopy;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use common\components\Platform\Core\Permission\IntentAccessService;

/**
 * Match claro a un intent: ofrece el botón. El flow arranca cuando la persona lo toca.
 */
final class ClaraRoutingHandler
{
    /**
     * @return array<string, mixed>
     */
    public static function handleSingle(string $content, string $intentId, int $userId): array
    {
        return self::offerButton($intentId, $content, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public static function offerButton(string $intentId, string $content, int $userId): array
    {
        $intentId = trim($intentId);
        if ($intentId === '' || $userId <= 0 || !IntentAccessService::userCanExecuteIntent($userId, $intentId)) {
            $msg = AssistantChannelCopy::t('intent_not_allowed');

            return [
                'kind' => 'message',
                'text' => $msg,
                'success' => false,
                'error' => $msg,
            ];
        }

        $catalog = UiActionCatalog::forUser($userId);
        $item = $catalog->byActionId[$intentId] ?? null;
        $label = self::labelFor($intentId, $item);
        $text = $item instanceof UiActionCatalogItem
            ? IntentMatchLaunchCopy::forOpenAction($item)
            : $label;
        if ($text === '') {
            $text = $label;
        }

        $button = [
            'label' => $label,
            'intent_id' => $intentId,
        ];
        $origin = trim($content);
        if ($origin !== '') {
            $button['content'] = $origin;
        }

        return AssistantContextAssemblyService::attachDebugIfEnabled(
            AssistantEnvelope::interactive($text, [$button])
        );
    }

    /**
     * @param UiActionCatalogItem|null $item
     */
    private static function labelFor(string $intentId, $item): string
    {
        if ($item instanceof UiActionCatalogItem && trim($item->display_name) !== '') {
            return trim($item->display_name);
        }

        $manifest = YamlIntentManifestLoader::load($intentId);
        $name = is_array($manifest) ? trim((string) ($manifest['action_name'] ?? '')) : '';

        return $name !== '' ? $name : $intentId;
    }
}
