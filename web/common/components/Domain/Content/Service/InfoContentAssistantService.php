<?php

namespace common\components\Domain\Content\Service;

use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;

/**
 * Puente entre el asistente y el contenido informativo.
 * Resuelve artículos por texto del usuario y los formatea como envelope.
 */
final class InfoContentAssistantService
{
    /**
     * Intenta resolver un artículo informativo desde el texto del usuario.
     * Retorna un envelope listo si hay match, null si no.
     *
     * @return array<string, mixed>|null
     */
    public static function tryResolveFromText(string $text, int $userId, ?int $idEfector = null, ?int $idProvincia = null): ?array
    {
        $article = InfoContentResolverService::resolveByText($text, $idEfector, $idProvincia);
        if ($article === null) {
            return null;
        }

        return self::envelopeFromArticle($article);
    }

    /**
     * Resuelve un artículo por topic directo (para llamadas desde intents/shortcuts).
     *
     * @return array<string, mixed>|null
     */
    public static function resolveByTopic(string $topic, ?int $idEfector = null, ?int $idProvincia = null): ?array
    {
        $article = InfoContentResolverService::resolve($topic, $idEfector, $idProvincia);
        if ($article === null) {
            return null;
        }

        return self::envelopeFromArticle($article);
    }

    /**
     * @return array<string, mixed>
     */
    private static function envelopeFromArticle(\common\models\InfoContentArticle $article): array
    {
        $body = trim($article->body);
        $title = trim($article->title);
        $text = $title !== '' ? "**{$title}**\n\n{$body}" : $body;

        return AssistantEnvelope::message($text);
    }
}
