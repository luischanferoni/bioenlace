<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Core\DataAccess\DataAccessMetricDiscoveryService;
use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\components\Platform\Core\Permission\IntentManifestIndex;
use common\components\Platform\Core\Permission\IntentMetricIndex;
use common\components\Platform\Core\Permission\PermissionCatalogService;

/**
 * Intents con metric_id fijo (sustitutos de data-access.info|listar por dominio).
 */
final class IntentMetricCatalogSupport
{
    public static function isMetricBoundIntent(string $intentId): bool
    {
        return IntentMetricIndex::metricForIntent(trim($intentId)) !== null;
    }

    public static function channelForIntentId(string $intentId): ?string
    {
        $meta = IntentManifestIndex::get(trim($intentId));
        if ($meta === null) {
            return null;
        }

        return match (trim((string) ($meta['operation'] ?? ''))) {
            'info' => DataAccessMetricDiscoveryService::CHANNEL_INFO,
            'list' => DataAccessMetricDiscoveryService::CHANNEL_LISTAR,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function openUiDefForIntent(string $intentId): ?array
    {
        $intentId = trim($intentId);
        $metricId = IntentMetricIndex::metricForIntent($intentId);
        if ($metricId === null) {
            return null;
        }

        $channel = self::channelForIntentId($intentId);
        if ($channel === null) {
            return null;
        }

        $actionId = $channel === DataAccessMetricDiscoveryService::CHANNEL_LISTAR
            ? 'data-access.listar'
            : 'data-access.info';

        return [
            'action_id' => $actionId,
            'params' => [
                'metric_id' => $metricId,
                'id_efector' => 'draft.id_efector',
            ],
        ];
    }

    public static function displayLabelForIntent(string $intentId): string
    {
        $manifest = (new PermissionCatalogService())->buildIntentFieldManifest(trim($intentId));
        if ($manifest === null) {
            return '';
        }
        $label = trim((string) ($manifest['action_name'] ?? ''));

        return $label !== '' ? $label : trim((string) ($manifest['intent_id'] ?? ''));
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function applyDraftHydrator(string $intentId, array &$body): void
    {
        if (!self::isMetricBoundIntent($intentId)) {
            return;
        }

        $metricId = IntentMetricIndex::metricForIntent($intentId);
        if ($metricId === null) {
            return;
        }

        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $draft['metric_id'] = $metricId;
        $body['draft'] = $draft;
    }

    public static function userCanAccessMetricIntent(int $userId, string $metricId): bool
    {
        $intentId = IntentMetricIndex::intentForMetric(trim($metricId));
        if ($intentId === null) {
            return false;
        }

        return BioenlaceAccessChecker::userCanPermissionKey($userId, $intentId);
    }
}
