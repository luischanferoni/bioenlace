<?php

namespace common\components\Domain\Content\Service;

use common\components\Ai\IAManager;
use common\components\Platform\Assistant\Context\AssistantContextAssemblyService;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannel;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannelConfig;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusState;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuidePromptAssembler;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Core\Permission\IntentAccessService;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;
use common\models\InfoContentArticle;
use Yii;

/**
 * Puente entre el asistente y el contenido informativo.
 * Resuelve artículos, responde con IA anclada a la fuente y adjunta CTA RBAC.
 */
final class InfoContentAssistantService
{
    /**
     * @return array<string, mixed>|null
     */
    public static function tryResolveFromText(string $text, int $userId, ?int $idEfector = null, ?int $idProvincia = null): ?array
    {
        $article = InfoContentResolverService::resolveByText($text, $idEfector, $idProvincia, $userId);
        if ($article === null) {
            return null;
        }

        return self::envelopeFromArticle($article, $text, $userId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resolveByTopic(
        string $topic,
        ?int $idEfector = null,
        ?int $idProvincia = null,
        int $userId = 0
    ): ?array {
        $article = InfoContentResolverService::resolve($topic, $idEfector, $idProvincia);
        if ($article === null) {
            return null;
        }
        if ($userId > 0 && !InfoContentResolverService::isVisibleToUser($article, $userId)) {
            return null;
        }

        return self::envelopeFromArticle($article, $topic, $userId);
    }

    public static function buildArticlePrompt(string $userQuestion, string $title, string $body, int $userId = 0): string
    {
        return GuidePromptAssembler::build(
            $userQuestion,
            $userId,
            new GuideFocusState(),
            null,
            '',
            GuideChannelConfig::formatArticleContent($title, $body)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function tryAnswerFromHisContext(string $content, int $userId): ?array
    {
        if ($userId <= 0 || ChatPreprocessContext::contextAreas() === []) {
            return null;
        }

        return GuideChannel::handle($content, $userId);
    }

    /**
     * Artículo informativo sin llamada a IA (camino directo 1 IA total).
     *
     * @return array<string, mixed>|null
     */
    public static function envelopeFromArticleDirect(
        string $topic,
        int $userId,
        string $userQuestion = ''
    ): ?array {
        $article = InfoContentResolverService::resolve($topic);
        if ($article === null) {
            return null;
        }
        if ($userId > 0 && !InfoContentResolverService::isVisibleToUser($article, $userId)) {
            return null;
        }

        $text = GuideChannelConfig::formatArticleContent(
            trim((string) $article->title),
            trim((string) $article->body)
        );
        if ($text === '') {
            return null;
        }

        return self::envelopeWithTextAndCta($article, $text, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private static function envelopeFromArticle(InfoContentArticle $article, string $userQuestion, int $userId): array
    {
        $body = trim((string) $article->body);
        $title = trim((string) $article->title);
        $text = self::generateAnswer($userQuestion, $title, $body, $userId);
        if ($text === null) {
            return GuideChannel::iaFailureEnvelope();
        }

        return self::envelopeWithTextAndCta($article, $text, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private static function envelopeWithTextAndCta(InfoContentArticle $article, string $text, int $userId): array
    {
        $buttons = self::ctaButtons($article, $userId);

        if ($buttons === []) {
            return AssistantContextAssemblyService::attachDebugIfEnabled(
                AssistantEnvelope::message($text)
            );
        }

        return AssistantContextAssemblyService::attachDebugIfEnabled(
            AssistantEnvelope::interactive($text, $buttons)
        );
    }

    private static function generateAnswer(string $userQuestion, string $title, string $body, int $userId): ?string
    {
        $prompt = self::buildArticlePrompt($userQuestion, $title, $body, $userId);
        $text = self::consultGuideIa($prompt);

        return $text !== '' ? $text : null;
    }

    private static function consultGuideIa(string $prompt): string
    {
        try {
            $raw = IAManager::consultarIA($prompt, 'asistente-guide', 'text-generation');
            if (is_string($raw) && trim($raw) !== '') {
                return trim($raw);
            }
            if (is_array($raw) && isset($raw['text'])) {
                return trim((string) $raw['text']);
            }
        } catch (\Throwable $e) {
            Yii::warning('InfoContentAssistantService IA: ' . $e->getMessage(), 'asistente');
        }

        return '';
    }

    /**
     * @return list<array{label: string, intent_id: string}>
     */
    private static function ctaButtons(InfoContentArticle $article, int $userId): array
    {
        $intentIds = InfoContentResolverService::effectiveIntentIds($article);
        if ($intentIds === [] || $userId <= 0) {
            return [];
        }

        $catalog = UiActionCatalog::forUser($userId);
        $buttons = [];
        foreach ($intentIds as $intentId) {
            if (!IntentAccessService::userCanExecuteIntent($userId, $intentId)) {
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
