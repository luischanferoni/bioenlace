<?php

namespace common\components\Domain\Content\Service;

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
     *
     * @return InfoContentArticle|null
     */
    public static function resolveByText(string $text, ?int $idEfector = null, ?int $idProvincia = null): ?InfoContentArticle
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        if ($text === '') {
            return null;
        }

        $candidates = InfoContentArticle::find()
            ->where(['activo' => true])
            ->orderBy(['priority' => SORT_DESC])
            ->all();

        $bestArticle = null;
        $bestScore = 0;

        foreach ($candidates as $article) {
            $score = self::scoreArticleAgainstText($article, $text);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestArticle = $article;
            }
        }

        if ($bestArticle === null) {
            return null;
        }

        $topic = $bestArticle->topic;

        return self::resolve($topic, $idEfector, $idProvincia);
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

    private static function scoreArticleAgainstText(InfoContentArticle $article, string $text): int
    {
        $score = 0;
        $topicNorm = mb_strtolower(trim($article->topic), 'UTF-8');
        if (mb_strpos($text, $topicNorm) !== false) {
            $score += 10;
        }

        foreach ($article->getKeywordList() as $kw) {
            $kwNorm = mb_strtolower(trim($kw), 'UTF-8');
            if ($kwNorm !== '' && mb_strpos($text, $kwNorm) !== false) {
                $score += 5;
            }
        }

        $titleNorm = mb_strtolower(trim($article->title), 'UTF-8');
        if (mb_strpos($text, $titleNorm) !== false) {
            $score += 8;
        }

        return $score;
    }
}
