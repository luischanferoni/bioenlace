<?php

namespace common\components\Domain\Content\Service;

use common\components\Ai\IAManager;
use common\components\Platform\Assistant\Context\AssistantContextAssemblyService;
use common\components\Platform\Assistant\Chat\Channels\Informational\InformationalChannelConfig;
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
     * Intenta resolver un artículo informativo desde el texto del usuario.
     * Retorna un envelope listo si hay match visible, null si no.
     *
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
     * Resuelve un artículo por topic directo (para llamadas desde intents/shortcuts).
     *
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

    /**
     * Prompt listo para IA informational anclada a fuente.
     */
    public static function buildArticlePrompt(string $userQuestion, string $title, string $body, int $userId = 0): string
    {
        $parts = [
            rtrim(InformationalChannelConfig::stablePrompt()),
        ];

        if ($userId > 0) {
            $hisContext = AssistantContextAssemblyService::assembleForChannel('informational', $userId);
            if (!$hisContext->isEmpty()) {
                $parts[] = '';
                $parts[] = $hisContext->promptSection;
            }
        }

        $parts[] = '';
        $parts[] = InformationalChannelConfig::formatSourceBlock($title, $body);
        $parts[] = '';
        $parts[] = 'Pregunta del usuario:';
        $parts[] = trim($userQuestion);

        return implode("\n", $parts);
    }

    /**
     * Respuesta informational sin artículo cuando preprocess pidió áreas HIS.
     *
     * @return array<string, mixed>|null
     */
    public static function tryAnswerFromHisContext(string $content, int $userId): ?array
    {
        if ($userId <= 0 || ChatPreprocessContext::contextAreas() === []) {
            return null;
        }

        $message = ChatPreprocessContext::normalizedText();
        if ($message === '') {
            $message = trim($content);
        }
        if ($message === '') {
            return null;
        }

        $parts = [rtrim(InformationalChannelConfig::stablePrompt())];
        $hisContext = AssistantContextAssemblyService::assembleForChannel('informational', $userId);
        if (!$hisContext->isEmpty()) {
            $parts[] = '';
            $parts[] = $hisContext->promptSection;
        }

        $parts[] = '';
        $parts[] = 'Pregunta del usuario:';
        $parts[] = $message;

        $text = self::consultInformationalIa(implode("\n", $parts));
        if ($text === '') {
            return null;
        }

        return AssistantContextAssemblyService::attachDebugIfEnabled(
            AssistantEnvelope::message($text)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function envelopeFromArticle(InfoContentArticle $article, string $userQuestion, int $userId): array
    {
        $body = trim((string) $article->body);
        $title = trim((string) $article->title);
        $text = self::generateAnswer($userQuestion, $title, $body, $userId);
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

    /**
     * IA anclada a fuente; si falla → dump del artículo (no inventar).
     */
    private static function generateAnswer(string $userQuestion, string $title, string $body, int $userId): string
    {
        $fallback = $title !== '' ? "**{$title}**\n\n{$body}" : $body;
        $prompt = self::buildArticlePrompt($userQuestion, $title, $body, $userId);

        $text = self::consultInformationalIa($prompt);
        if ($text !== '') {
            return $text;
        }

        return $fallback;
    }

    private static function consultInformationalIa(string $prompt): string
    {
        try {
            $raw = IAManager::consultarIA($prompt, 'asistente-informational', 'text-generation');
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
