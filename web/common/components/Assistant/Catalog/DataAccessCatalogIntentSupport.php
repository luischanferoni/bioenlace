<?php

namespace common\components\Assistant\Catalog;

use common\components\Assistant\SubIntentEngine\FlowDraftHydratorRegistry;
use common\components\Core\DataAccess\DataAccessMetricDiscoveryService;

/**
 * Intents DataAccess expuestos vía {@see DataAccessUiActionCatalog} (sin YAML flow).
 */
final class DataAccessCatalogIntentSupport
{
    /** @var list<string> */
    private const CATALOG_INTENT_IDS = [
        'data-access.info',
        'data-access.listar',
        'data-access.editar',
    ];

    public static function isCatalogOnlyIntent(string $intentId): bool
    {
        return in_array(trim($intentId), self::CATALOG_INTENT_IDS, true);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function applyDraftHydrator(string $intentId, array &$body): void
    {
        $intentId = trim($intentId);
        if (!self::isCatalogOnlyIntent($intentId)) {
            return;
        }

        if ($intentId === 'data-access.editar') {
            FlowDraftHydratorRegistry::apply('data_access.edit_flow', $body, ['channel' => 'editar']);

            return;
        }

        $channel = DataAccessMetricDiscoveryService::channelForIntentId($intentId);
        if ($channel !== null) {
            FlowDraftHydratorRegistry::apply('data_access.metric_flow', $body, ['channel' => $channel]);
        }
    }

    /**
     * Definición open_ui equivalente al YAML eliminado (params desde draft).
     *
     * @return array<string, mixed>|null
     */
    public static function openUiDefForIntent(string $intentId): ?array
    {
        $intentId = trim($intentId);
        if ($intentId === 'data-access.info' || $intentId === 'data-access.listar') {
            return [
                'action_id' => $intentId,
                'params' => [
                    'metric_id' => 'draft.metric_id',
                    'id_efector' => 'draft.id_efector',
                    'servicio_rol' => 'draft.servicio_rol',
                    'servicio_rol_mention' => 'draft.servicio_rol_mention',
                    'sexo_biologico' => 'draft.sexo_biologico',
                ],
            ];
        }
        if ($intentId === 'data-access.editar') {
            return [
                'action_id' => 'data-access.editar',
                'params' => [
                    'surface_id' => 'draft.surface_id',
                    'id_efector' => 'draft.id_efector',
                    'step' => 'draft.edit_step',
                    'aspect_ids' => 'draft.aspect_ids',
                ],
            ];
        }

        return null;
    }

    public static function displayLabelForIntent(string $intentId): string
    {
        $def = DataAccessUiActionCatalog::definitionByActionId(trim($intentId));
        if ($def === null) {
            return '';
        }
        $label = trim((string) ($def['action_name'] ?? ''));

        return $label !== '' ? $label : trim((string) ($def['action_id'] ?? ''));
    }
}
