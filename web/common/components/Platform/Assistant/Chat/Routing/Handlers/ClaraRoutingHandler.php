<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Platform\Assistant\Chat\Channels\Operational\OperationalChannel;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingDecision;
use common\components\Platform\Core\Permission\IntentAccessService;
use common\components\Platform\Core\Product\ProductMetadataPaths;
use Yii;

/**
 * Routing clara: un intent → flow; varios → desambiguación interactive.
 */
final class ClaraRoutingHandler
{
    /**
     * @return array<string, mixed>
     */
    public static function handleSingle(string $content, string $intentId, int $userId): array
    {
        return OperationalChannel::handle($content, $intentId, $userId);
    }

    /**
     * @param array<string, mixed> $firstIa
     * @return array<string, mixed>|null
     */
    public static function handleMultiple(
        SmartCatalogRoutingDecision $decision,
        array $firstIa,
        string $content,
        int $userId
    ): ?array {
        $buttons = self::buildIntentButtons($decision->intentIds, $userId);
        if ($buttons === []) {
            Yii::warning(
                'ClaraRoutingHandler: intents claros sin permisos RBAC; degradando a legacy.',
                'asistente'
            );

            return null;
        }

        if (count($buttons) === 1) {
            return self::handleSingle($content, $buttons[0]['intent_id'], $userId);
        }

        $intro = trim((string) ($firstIa['necesidad_usuario'] ?? ''));
        if ($intro === '') {
            $config = AssistantMetadataLoader::load(ProductMetadataPaths::smartCatalogRoutingFile());
            $intro = AssistantMetadataLoader::dotString($config, 'clara_multi_intent_intro');
        }
        if ($intro === '') {
            $intro = 'Encontré varias acciones posibles. Elegí la que mejor se ajuste:';
        }

        return AssistantEnvelope::interactive($intro, $buttons);
    }

    /**
     * @param list<string> $intentIds
     * @return list<array{label: string, intent_id: string}>
     */
    private static function buildIntentButtons(array $intentIds, int $userId): array
    {
        if ($userId <= 0 || $intentIds === []) {
            return [];
        }

        $catalog = UiActionCatalog::forUser($userId);
        $buttons = [];
        foreach ($intentIds as $intentId) {
            $intentId = trim($intentId);
            if ($intentId === '' || !IntentAccessService::userCanExecuteIntent($userId, $intentId)) {
                continue;
            }
            $item = $catalog->byActionId[$intentId] ?? null;
            $label = $item instanceof UiActionCatalogItem && $item->display_name !== ''
                ? $item->display_name
                : $intentId;
            $buttons[] = [
                'label' => $label,
                'intent_id' => $intentId,
            ];
        }

        return $buttons;
    }
}
