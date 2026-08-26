<?php

namespace common\components\Domain\Content\Service;

use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;
use common\components\Platform\Core\Permission\IntentAccessService;
use common\models\InfoContentArticle;

/**
 * Resuelve el artículo informativo más específico para un tema dado,
 * con fallback jerárquico: efector → provincia → producto.
 */
final class InfoContentResolverService
{
    /**
     * @return InfoContentArticle|null Artículo más específico activo, o null si no hay.
     */
    public static function resolve(string $topic, ?int $idEfector = null, ?int $idProvincia = null): ?InfoContentArticle
    {
        $topic = trim($topic);
        if ($topic === '') {
            return null;
        }

        if ($idEfector !== null && $idEfector > 0) {
            $article = self::findByScope($topic, InfoContentArticle::SCOPE_EFECTOR, null, $idEfector);
            if ($article !== null) {
                return $article;
            }
            if ($idProvincia === null || $idProvincia <= 0) {
                $idProvincia = self::provinciaFromEfector($idEfector);
            }
        }

        if ($idProvincia !== null && $idProvincia > 0) {
            $article = self::findByScope($topic, InfoContentArticle::SCOPE_PROVINCIA, $idProvincia, null);
            if ($article !== null) {
                return $article;
            }
        }

        return self::findByScope($topic, InfoContentArticle::SCOPE_PRODUCTO, null, null);
    }

    /**
     * Busca artículo por topic + texto del usuario (fuzzy por keywords).
     * Si $userId > 0, omite artículos cuyos CTA el usuario no puede ejecutar.
     *
     * @return InfoContentArticle|null
     */
    public static function resolveByText(
        string $text,
        ?int $idEfector = null,
        ?int $idProvincia = null,
        int $userId = 0
    ): ?InfoContentArticle {
        $folded = ChatChannelPolicy::fold($text);
        if ($folded === '') {
            return null;
        }

        $candidates = InfoContentArticle::find()
            ->where(['activo' => true])
            ->orderBy(['priority' => SORT_DESC])
            ->all();

        /** @var list<array{article: InfoContentArticle, score: int}> $ranked */
        $ranked = [];
        foreach ($candidates as $article) {
            $score = self::scoreArticleAgainstText($article, $folded);
            if ($score > 0) {
                $ranked[] = ['article' => $article, 'score' => $score];
            }
        }

        usort($ranked, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $seenTopics = [];
        foreach ($ranked as $row) {
            $topic = $row['article']->topic;
            if (isset($seenTopics[$topic])) {
                continue;
            }
            $seenTopics[$topic] = true;

            $resolved = self::resolve($topic, $idEfector, $idProvincia);
            if ($resolved === null) {
                continue;
            }
            if ($userId > 0 && !self::isVisibleToUser($resolved, $userId)) {
                continue;
            }

            return $resolved;
        }

        return null;
    }

    /**
     * Intent CTA efectivos: los del artículo, o los del producto del mismo topic.
     *
     * @return list<string>
     */
    public static function effectiveIntentIds(InfoContentArticle $article): array
    {
        $own = $article->getIntentIdList();
        if ($own !== []) {
            return $own;
        }
        if ($article->scope === InfoContentArticle::SCOPE_PRODUCTO) {
            return [];
        }

        $product = self::findByScope($article->topic, InfoContentArticle::SCOPE_PRODUCTO, null, null);

        return $product !== null ? $product->getIntentIdList() : [];
    }

    /**
     * Sin intents → visible. Con intents → al menos uno ejecutable por el usuario.
     */
    public static function isVisibleToUser(InfoContentArticle $article, int $userId): bool
    {
        $intentIds = self::effectiveIntentIds($article);
        if ($intentIds === []) {
            return true;
        }
        if ($userId <= 0) {
            return false;
        }

        foreach ($intentIds as $intentId) {
            if (IntentAccessService::userCanExecuteIntent($userId, $intentId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string> Todos los topics activos distintos.
     */
    public static function allActiveTopics(): array
    {
        $rows = InfoContentArticle::find()
            ->select('topic')
            ->where(['activo' => true])
            ->distinct()
            ->orderBy(['topic' => SORT_ASC])
            ->column();

        return array_values($rows);
    }

    private static function findByScope(string $topic, string $scope, ?int $idProvincia, ?int $idEfector): ?InfoContentArticle
    {
        $q = InfoContentArticle::find()
            ->where(['topic' => $topic, 'scope' => $scope, 'activo' => true])
            ->orderBy(['priority' => SORT_DESC]);

        if ($scope === InfoContentArticle::SCOPE_EFECTOR) {
            $q->andWhere(['id_efector' => $idEfector]);
        } elseif ($scope === InfoContentArticle::SCOPE_PROVINCIA) {
            $q->andWhere(['id_provincia' => $idProvincia]);
        }

        return $q->one();
    }

    private static function provinciaFromEfector(int $idEfector): ?int
    {
        try {
            $efector = \common\models\Efector::findOne($idEfector);
            if ($efector === null || empty($efector->id_localidad)) {
                return null;
            }
            $localidad = \common\models\Localidad::findOne($efector->id_localidad);
            if ($localidad === null) {
                return null;
            }
            $depto = \common\models\Departamento::findOne($localidad->id_departamento);

            return $depto !== null ? (int) $depto->id_provincia : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * $foldedText ya viene en fold (minúsculas sin tildes).
     */
    private static function scoreArticleAgainstText(InfoContentArticle $article, string $foldedText): int
    {
        $score = 0;
        $topicNorm = ChatChannelPolicy::fold((string) $article->topic);
        if ($topicNorm !== '' && self::textMatchesToken($foldedText, $topicNorm)) {
            $score += 10;
        }

        foreach ($article->getKeywordList() as $kw) {
            $kwNorm = ChatChannelPolicy::fold($kw);
            if ($kwNorm !== '' && self::textMatchesToken($foldedText, $kwNorm)) {
                $score += 5;
            }
        }

        $titleNorm = ChatChannelPolicy::fold((string) $article->title);
        if ($titleNorm !== '' && mb_strpos($foldedText, $titleNorm) !== false) {
            $score += 8;
        }

        return $score;
    }

    /**
     * Match tolerante: substring + stem suelto (representar ↔ representacion).
     */
    public static function textMatchesToken(string $foldedText, string $foldedToken): bool
    {
        if ($foldedToken === '' || $foldedText === '') {
            return false;
        }
        if (mb_strpos($foldedText, $foldedToken) !== false) {
            return true;
        }

        $stem = self::looseStem($foldedToken);
        if ($stem !== $foldedToken && mb_strlen($stem) >= 5 && mb_strpos($foldedText, $stem) !== false) {
            return true;
        }

        // Token del texto vs stem del keyword (ej. "representar" en texto vs keyword "representacion").
        foreach (preg_split('/\s+/u', $foldedText) ?: [] as $word) {
            $word = trim((string) $word);
            if ($word === '') {
                continue;
            }
            $wordStem = self::looseStem($word);
            if ($stem !== '' && $wordStem === $stem && mb_strlen($stem) >= 5) {
                return true;
            }
        }

        return false;
    }

    public static function looseStem(string $word): string
    {
        $w = trim($word);
        if ($w === '') {
            return '';
        }

        $suffixes = ['ciones', 'cion', 'siones', 'sion', 'mente', 'ando', 'endo', 'ados', 'adas', 'ar', 'er', 'ir', 'es', 's'];
        foreach ($suffixes as $suf) {
            $len = mb_strlen($suf);
            if (mb_strlen($w) - $len < 4) {
                continue;
            }
            if (str_ends_with($w, $suf)) {
                $w = mb_substr($w, 0, mb_strlen($w) - $len);
                break;
            }
        }

        // Vocal residual tras morfología (representa → represent).
        if (mb_strlen($w) >= 6 && preg_match('/[aeiou]$/u', $w)) {
            $w = mb_substr($w, 0, mb_strlen($w) - 1);
        }

        return $w;
    }
}
