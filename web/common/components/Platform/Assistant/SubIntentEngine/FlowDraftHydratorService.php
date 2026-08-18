<?php

namespace common\components\Platform\Assistant\SubIntentEngine;

use common\components\Platform\Assistant\Catalog\DataAccessCatalogIntentSupport;
use common\components\Platform\Assistant\Catalog\IntentMetricCatalogSupport;
use common\components\Platform\Assistant\Catalog\YamlIntentManifestLoader;
use common\components\Platform\Assistant\Service\AssistantDraftNormalizer;
use common\components\Platform\Core\Permission\IntentSubjectResolutionService;

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
        $before = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        self::applyDeclaredHydrator($intentId, $body);
        $after = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $delta = self::scalarDraftDiff($before, $after);
        if ($delta !== []) {
            $body['_hydrator_draft_delta'] = $delta;
        } else {
            unset($body['_hydrator_draft_delta']);
        }
    }

    /**
     * Claves escalares que el hydrator agregó o cambió (para `session.draft_delta` del cliente).
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, string>
     */
    public static function scalarDraftDiff(array $before, array $after): array
    {
        $delta = [];
        foreach ($after as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $key = trim($key);
            if ($key === '' || $key[0] === '_' || $key === 'assistant_text') {
                continue;
            }
            $scalar = AssistantDraftNormalizer::asOptionalString($value);
            if ($scalar === null) {
                continue;
            }
            $prev = AssistantDraftNormalizer::asOptionalString($before[$key] ?? null);
            if ($prev === $scalar) {
                continue;
            }
            $delta[$key] = $scalar;
        }

        return $delta;
    }

    /**
     * El delta del hydrator no pisa una selección explícita del motor.
     *
     * @param array<string, mixed> $motor
     * @param array<string, string> $delta
     * @return array<string, mixed>
     */
    public static function mergeDeltaIntoMotor(array $motor, array $delta): array
    {
        if ($delta === []) {
            return $motor;
        }
        $existing = $motor['draft_delta'] ?? [];
        if ($existing instanceof \stdClass) {
            $existing = (array) $existing;
        }
        if (!is_array($existing)) {
            $existing = [];
        }
        $motor['draft_delta'] = array_merge($delta, $existing);

        return $motor;
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function applyDeclaredHydrator(string $intentId, array &$body): void
    {
        if (DataAccessCatalogIntentSupport::isCatalogOnlyIntent($intentId)) {
            DataAccessCatalogIntentSupport::applyDraftHydrator($intentId, $body);

            return;
        }

        if (IntentMetricCatalogSupport::isMetricBoundIntent($intentId)) {
            IntentMetricCatalogSupport::applyDraftHydrator($intentId, $body);

            (new IntentSubjectResolutionService())->applyToBody($intentId, $body);

            return;
        }

        $manifest = YamlIntentManifestLoader::load($intentId);
        if ($manifest === null) {
            return;
        }

        $cfg = isset($manifest['draft_hydrator']) && is_array($manifest['draft_hydrator'])
            ? $manifest['draft_hydrator']
            : null;
        if ($cfg !== null) {
            $handlerId = trim((string) ($cfg['handler'] ?? ''));
            if ($handlerId !== '') {
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

        (new IntentSubjectResolutionService())->applyToBody($intentId, $body);
    }
}
