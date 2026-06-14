<?php

namespace common\components\Platform\Core\DataAccess;

use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Domain\Organization\Service\Efectores\OrganizationEfectorAccess;
use common\components\Domain\Organization\Service\Servicios\ServicioMencionLookupService;

/**
 * Enriquece draft para intents genéricos data-access.info / data-access.listar.
 *
 * @param array<string, mixed> $body
 * @param array<string, mixed> $options channel: info|listar
 */
final class DataAccessFlowDraftHydrator
{
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        $channel = trim((string) ($options['channel'] ?? ''));
        if ($channel === '') {
            $intentId = trim((string) ($body['intent_id'] ?? ''));
            $channel = DataAccessMetricDiscoveryService::channelForIntentId($intentId) ?? '';
        }
        if ($channel === '') {
            throw new \InvalidArgumentException('data_access.metric_flow requiere channel info|listar.');
        }

        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $content = trim((string) ($body['content'] ?? ''));

        if (trim((string) ($draft['metric_id'] ?? '')) === '') {
            $discovery = new DataAccessMetricDiscoveryService();
            $metricId = $discovery->resolveMetricId(
                $channel,
                $content,
                ChatPreprocessContext::extractions()
            );
            if ($metricId !== null) {
                $draft['metric_id'] = $metricId;
            }
        }

        if (!isset($draft['id_efector']) || trim((string) $draft['id_efector']) === '') {
            $fromDraft = isset($draft['id_efector']) ? (int) $draft['id_efector'] : 0;
            $idEfector = OrganizationEfectorAccess::resolveIdEfector($fromDraft > 0 ? $fromDraft : null);
            if ($idEfector > 0) {
                $draft['id_efector'] = (string) $idEfector;
            }
        }

        $metricId = trim((string) ($draft['metric_id'] ?? ''));
        if ($metricId !== '') {
            self::hydrateFiltersFromContent($draft, $metricId, $content);
        }

        $body['draft'] = $draft;
    }

    /**
     * @param array<string, mixed> $draft
     */
    private static function hydrateFiltersFromContent(array &$draft, string $metricId, string $content): void
    {
        $catalog = new AttributeGroupCatalog();
        $plan = $catalog->getMetricQueryPlan($metricId);
        $filters = is_array($plan) && isset($plan['filters']) && is_array($plan['filters'])
            ? $plan['filters']
            : [];

        foreach ($filters as $filterKey => $def) {
            if (!is_string($filterKey) || !is_array($def)) {
                continue;
            }
            $resolver = trim((string) ($def['resolver'] ?? ''));
            if ($resolver === 'servicio_rol_from_mention') {
                self::hydrateServicioMention($draft, $content);
            } elseif ($resolver === 'sexo_biologico') {
                self::hydrateSexoBiologico($draft, $content, $catalog);
            }
        }
    }

    /**
     * @param array<string, mixed> $draft
     */
    private static function hydrateServicioMention(array &$draft, string $content): void
    {
        if (trim((string) ($draft['servicio_rol_mention'] ?? '')) !== '') {
            return;
        }

        $mention = self::servicioMentionFromExtractions();
        if ($mention === null && $content !== '') {
            $mention = $content;
        }
        if ($mention === null || $mention === '') {
            return;
        }

        $ids = (new ServicioMencionLookupService())->idsDesdeMencion($mention);
        if ($ids === []) {
            return;
        }

        $draft['servicio_rol_mention'] = $mention;
        if (trim((string) ($draft['servicio_rol'] ?? '')) === '') {
            $draft['servicio_rol'] = $mention;
        }
    }

    /**
     * @param array<string, mixed> $draft
     */
    private static function hydrateSexoBiologico(array &$draft, string $content, AttributeGroupCatalog $catalog): void
    {
        if (trim((string) ($draft['sexo_biologico'] ?? '')) !== '') {
            return;
        }

        $mention = self::sexoMentionFromExtractions($content);
        if ($mention === null) {
            return;
        }

        $code = $catalog->resolveSexoBiologicoFromMention($mention);
        if ($code !== null) {
            $draft['sexo_biologico'] = (string) $code;
        }
    }

    private static function servicioMentionFromExtractions(): ?string
    {
        foreach (ChatPreprocessContext::extractions() as $ex) {
            if (!is_array($ex)) {
                continue;
            }
            if (trim((string) ($ex['category'] ?? '')) !== 'servicio') {
                continue;
            }
            $span = trim((string) ($ex['span'] ?? ''));

            return $span !== '' ? $span : null;
        }

        return null;
    }

    private static function sexoMentionFromExtractions(string $content): ?string
    {
        foreach (ChatPreprocessContext::extractions() as $ex) {
            if (!is_array($ex)) {
                continue;
            }
            $cat = trim((string) ($ex['category'] ?? ''));
            if ($cat !== 'persona' && $cat !== 'profesional') {
                continue;
            }
            $span = trim((string) ($ex['span'] ?? ''));
            if ($span !== '') {
                return $span;
            }
        }

        if ($content !== '') {
            return $content;
        }

        return null;
    }
}
