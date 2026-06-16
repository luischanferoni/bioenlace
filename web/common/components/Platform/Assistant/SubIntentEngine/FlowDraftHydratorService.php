<?php

namespace common\components\Platform\Assistant\SubIntentEngine;

use common\components\Platform\Assistant\Catalog\DataAccessCatalogIntentSupport;
use common\components\Platform\Assistant\Catalog\IntentMetricCatalogSupport;
use common\components\Platform\Assistant\Catalog\YamlIntentManifestLoader;

/**
 * Aplica `draft_hydrator` declarado en el YAML del intent antes de {@see SubIntentEngine::process}.
 */
final class FlowDraftHydratorService
{
    /**
     * @param array<string, mixed> $body
     */
    public static function hydrateFromIntentManifest(string $intentId, array &$body): void
    {
        if (DataAccessCatalogIntentSupport::isCatalogOnlyIntent($intentId)) {
            DataAccessCatalogIntentSupport::applyDraftHydrator($intentId, $body);

            return;
        }

        if (IntentMetricCatalogSupport::isMetricBoundIntent($intentId)) {
            IntentMetricCatalogSupport::applyDraftHydrator($intentId, $body);

            return;
        }

        $manifest = YamlIntentManifestLoader::load($intentId);
        if ($manifest === null) {
            return;
        }

        $cfg = isset($manifest['draft_hydrator']) && is_array($manifest['draft_hydrator'])
            ? $manifest['draft_hydrator']
            : null;
        if ($cfg === null) {
            return;
        }

        $handlerId = trim((string) ($cfg['handler'] ?? ''));
        if ($handlerId === '') {
            return;
        }

        $options = isset($cfg['options']) && is_array($cfg['options']) ? $cfg['options'] : [];
        foreach ($cfg as $key => $value) {
            if ($key === 'handler' || $key === 'options') {
                continue;
            }
            $options[$key] = $value;
        }

        FlowDraftHydratorRegistry::apply($handlerId, $body, $options);
    }
}
