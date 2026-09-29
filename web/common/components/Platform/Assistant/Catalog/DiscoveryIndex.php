<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Domain\Content\Application\Service\InfoContentResolverService;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannelConfig;
use common\models\Content\InfoContentArticle;
use Yii;

/**
 * Índice único de discovery: tags (+ texto) → intents (YAML) y artículos (BD).
 *
 * No elige canal ni saltea Guide: solo produce hits para adjuntos.
 */
final class DiscoveryIndex
{
    /**
     * @param array<string, mixed> $firstIa
     */
    public static function match(
        array $firstIa,
        string $message = '',
        int $userId = 0,
        int $maxIntents = 4,
        int $maxArticles = 2
    ): DiscoveryResult {
        $needles = StateTagIndex::needles($firstIa, $message);
        $intentHits = StateTagIndex::match($needles);
        if (count($intentHits) > $maxIntents) {
            $intentHits = array_slice($intentHits, 0, $maxIntents);
        }

        $normalized = trim((string) ($firstIa['normalized_text'] ?? ''));
        if ($normalized === '') {
            $normalized = trim($message);
        }

        $idEfector = self::currentIdEfector();
        $articleRows = InfoContentResolverService::rankByTagsAndText(
            $needles,
            $normalized,
            $idEfector,
            null,
            $userId,
            $maxArticles
        );

        $articleHits = [];
        foreach ($articleRows as $row) {
            $articleHits[] = [
                'topic' => (string) $row['topic'],
                'score' => (int) $row['score'],
                'title' => (string) $row['title'],
            ];
        }

        return new DiscoveryResult($intentHits, $articleHits);
    }

    /**
     * Bloque de artículo listo para el prompt Guide (vacío si no hay hit).
     *
     * @param array<string, mixed> $firstIa
     */
    public static function formatTopArticleBlock(array $firstIa, string $message = '', int $userId = 0): string
    {
        $result = self::match($firstIa, $message, $userId, 1, 1);
        $topic = $result->primaryArticleTopic();
        if ($topic === '') {
            return '';
        }

        $idEfector = self::currentIdEfector();
        $article = InfoContentResolverService::resolve($topic, $idEfector, null);
        if ($article === null) {
            return '';
        }
        if ($userId > 0 && !InfoContentResolverService::isVisibleToUser($article, $userId)) {
            return '';
        }

        $formatted = GuideChannelConfig::formatArticleContent(
            (string) $article->title,
            (string) $article->body
        );
        if ($formatted === '') {
            return '';
        }

        return GuideChannelConfig::formatOptionalAttachment('article', $formatted);
    }

    /**
     * CTAs de intents descubiertos + intents del artículo top (si hay).
     *
     * @param array<string, mixed> $firstIa
     * @return list<string>
     */
    public static function intentIdsForCta(array $firstIa, string $message = '', int $userId = 0): array
    {
        $result = self::match($firstIa, $message, $userId);
        $ids = $result->intentIds(4);

        $topic = $result->primaryArticleTopic();
        if ($topic === '') {
            return $ids;
        }

        $article = InfoContentResolverService::resolve($topic, self::currentIdEfector(), null);
        if (!$article instanceof InfoContentArticle) {
            return $ids;
        }

        foreach (InfoContentResolverService::effectiveIntentIds($article) as $intentId) {
            $intentId = trim((string) $intentId);
            if ($intentId !== '' && !in_array($intentId, $ids, true)) {
                $ids[] = $intentId;
            }
        }

        return $ids;
    }

    private static function currentIdEfector(): ?int
    {
        try {
            if (!Yii::$app->has('user', true)) {
                return null;
            }
            $id = (int) Yii::$app->user->getIdEfector();

            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
