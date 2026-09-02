<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Domain\Content\Service\InfoContentAssistantService;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Context\AssistantContextAssemblyService;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingDecision;
use common\components\Platform\Core\Permission\IntentAccessService;

/**
 * Match directo: template de catálogo o artículo informativo sin 2ª IA.
 */
final class DirectMatchHandler
{
    /**
     * @return array<string, mixed>|null
     */
    public static function handle(SmartCatalogRoutingDecision $decision, string $content, int $userId): ?array
    {
        if ($decision->isDirectArticle()) {
            return InfoContentAssistantService::envelopeFromArticleDirect(
                $decision->articleTopic,
                $userId,
                $content
            );
        }

        if ($decision->isDirectTemplate()) {
            return self::envelopeFromTemplate($decision, $userId);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function envelopeFromTemplate(SmartCatalogRoutingDecision $decision, int $userId): array
    {
        $text = trim($decision->responseText);
        $ctaIntentId = trim((string) ($decision->catalogEntry?->ctaIntentId ?? ''));

        if ($ctaIntentId === '' || $userId <= 0 || !IntentAccessService::userCanExecuteIntent($userId, $ctaIntentId)) {
            return AssistantContextAssemblyService::attachDebugIfEnabled(
                AssistantEnvelope::message($text)
            );
        }

        $catalog = UiActionCatalog::forUser($userId);
        $item = $catalog->byActionId[$ctaIntentId] ?? null;
        $label = $item instanceof UiActionCatalogItem && $item->display_name !== ''
            ? $item->display_name
            : $ctaIntentId;

        return AssistantContextAssemblyService::attachDebugIfEnabled(
            AssistantEnvelope::interactive($text, [
                [
                    'label' => $label,
                    'intent_id' => $ctaIntentId,
                ],
            ])
        );
    }
}
