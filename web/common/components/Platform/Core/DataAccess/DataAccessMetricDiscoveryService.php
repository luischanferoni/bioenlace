<?php

namespace common\components\Platform\Core\DataAccess;

/**
 * Resuelve metric_id y keywords NL desde data-access-config (sin valores de atributos en intents).
 */
final class DataAccessMetricDiscoveryService
{
    public const CHANNEL_INFO = 'info';

    public const CHANNEL_LISTAR = 'listar';

    /** @var AttributeGroupCatalog */
    private $catalog;

    /** @var AttributePermissionEvaluator */
    private $permissions;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?AttributePermissionEvaluator $permissions = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->permissions = $permissions ?? new AttributePermissionEvaluator();
    }

    public static function channelForIntentId(string $intentId): ?string
    {
        $intentId = trim($intentId);
        if ($intentId === 'data-access.info') {
            return self::CHANNEL_INFO;
        }
        if ($intentId === 'data-access.listar') {
            return self::CHANNEL_LISTAR;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $extractions preprocess extractions
     */
    public function resolveMetricId(string $channel, string $content, array $extractions, ?PermissionContext $ctx = null): ?string
    {
        $channel = trim($channel);
        if ($channel === '') {
            return null;
        }

        $ctx = $ctx ?? PermissionContext::fromCurrentUser();
        $contentLower = mb_strtolower(trim($content), 'UTF-8');
        $bestId = null;
        $bestScore = 0;

        foreach ($this->catalog->listMetricsForDisplay() as $metricId => $def) {
            if (!is_string($metricId) || !is_array($def)) {
                continue;
            }
            if (!$this->userCanAccessMetric($ctx, $metricId, $channel)) {
                continue;
            }
            if (!$this->metricSupportsChannel($metricId, $channel)) {
                continue;
            }
            $score = $this->scoreMetric($metricId, $def, $contentLower, $extractions);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $metricId;
            }
        }

        return $bestScore > 0 ? $bestId : null;
    }

    /**
     * @return list<string>
     */
    public function assistantKeywordsForUser(int $userId, string $channel): array
    {
        $ctx = new PermissionContext($userId, $this->roleNamesForUser($userId));
        $out = [];
        foreach ($this->catalog->listMetricsForDisplay() as $metricId => $def) {
            if (!is_string($metricId) || !is_array($def)) {
                continue;
            }
            if (!$this->userCanAccessMetric($ctx, $metricId, $channel)) {
                continue;
            }
            if (!$this->metricSupportsChannel($metricId, $channel)) {
                continue;
            }
            foreach ($this->assistantKeywords($def) as $kw) {
                $out[] = $kw;
            }
        }

        return array_values(array_unique($out));
    }

    public function userCanAccessMetric(PermissionContext $ctx, string $metricId, string $channel): bool
    {
        $metric = $this->catalog->getMetric($metricId);
        if ($metric === null) {
            return false;
        }

        $required = $metric['required_groups'] ?? [];
        if (!is_array($required)) {
            return false;
        }
        foreach ($required as $group) {
            $group = trim((string) $group);
            if ($group === '') {
                continue;
            }
            if (!$this->permissions->can($ctx, $group, QueryOperation::AGGREGATE)) {
                return false;
            }
        }

        if ($channel === self::CHANNEL_LISTAR) {
            $readGroups = $metric['read_groups'] ?? [];
            if (!is_array($readGroups) || $readGroups === []) {
                return false;
            }
            foreach ($readGroups as $group) {
                $group = trim((string) $group);
                if ($group === '') {
                    continue;
                }
                if (!$this->permissions->can($ctx, $group, QueryOperation::READ)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function metricSupportsChannel(string $metricId, string $channel): bool
    {
        $plan = $this->catalog->getMetricOutputPlan($metricId);
        if ($plan === null) {
            return false;
        }
        $modes = isset($plan['modes']) && is_array($plan['modes']) ? $plan['modes'] : [];
        if ($modes === []) {
            return false;
        }

        if ($channel === self::CHANNEL_LISTAR) {
            return in_array(QueryOutputMode::ROWS, $modes, true);
        }

        return in_array(QueryOutputMode::AGGREGATE, $modes, true)
            || in_array(QueryOutputMode::GROUPED, $modes, true);
    }

    /**
     * @param array<string, mixed> $metricDef
     * @param list<array<string, mixed>> $extractions
     */
    private function scoreMetric(string $metricId, array $metricDef, string $contentLower, array $extractions): int
    {
        $score = 0;
        $metricIdLower = mb_strtolower($metricId, 'UTF-8');

        if ($contentLower !== '' && mb_stripos($contentLower, $metricIdLower) !== false) {
            $score += 15;
        }

        $label = mb_strtolower(trim((string) ($metricDef['label'] ?? '')), 'UTF-8');
        if ($label !== '' && $contentLower !== '' && mb_stripos($contentLower, $label) !== false) {
            $score += 25;
        }

        foreach ($this->assistantKeywords($metricDef) as $kw) {
            $kwLower = mb_strtolower(trim($kw), 'UTF-8');
            if ($kwLower !== '' && $contentLower !== '' && mb_stripos($contentLower, $kwLower) !== false) {
                $score += 20;
            }
        }

        foreach ($extractions as $ex) {
            if (!is_array($ex)) {
                continue;
            }
            $span = mb_strtolower(trim((string) ($ex['span'] ?? '')), 'UTF-8');
            if ($span === '') {
                continue;
            }
            if ($label !== '' && mb_stripos($label, $span) !== false) {
                $score += 10;
            }
            foreach ($this->assistantKeywords($metricDef) as $kw) {
                $kwLower = mb_strtolower(trim($kw), 'UTF-8');
                if ($kwLower !== '' && mb_stripos($kwLower, $span) !== false) {
                    $score += 10;
                }
            }
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $metricDef
     * @return list<string>
     */
    private function assistantKeywords(array $metricDef): array
    {
        return CatalogDefinitionHelper::keywords($metricDef);
    }

    /**
     * @return list<string>
     */
    private function roleNamesForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $assigned = \Yii::$app->authManager->getRolesByUser($userId);

        return is_array($assigned) ? array_keys($assigned) : [];
    }
}
