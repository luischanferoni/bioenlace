<?php

namespace common\components\IntentEngine;

use common\components\IntentCatalog\IntentCatalogService;

/**
 * Catálogo de UIs disponibles para un usuario (templates JSON existentes + RBAC).
 */
final class UiActionCatalog
{
    /** @var UiActionCatalogItem[] */
    public array $items;

    /** @var array<string, UiActionCatalogItem> */
    public array $byActionId;

    /**
     * @param UiActionCatalogItem[] $items
     * @param array<string, UiActionCatalogItem> $byActionId
     */
    private function __construct(array $items, array $byActionId)
    {
        $this->items = $items;
        $this->byActionId = $byActionId;
    }

    public static function forUser(int $userId): self
    {
        $raw = IntentCatalogService::getAvailableUiForUser($userId, true);
        $items = [];
        $byId = [];

        foreach ($raw as $a) {
            $actionId = isset($a['action_id']) ? (string) $a['action_id'] : '';
            if ($actionId === '') {
                continue;
            }

            $display = (string) ($a['action_name'] ?? $a['display_name'] ?? '');
            if ($display === '' || strncmp($display, 'RBAC:', 5) === 0) {
                $display = $actionId;
            }
            $desc = (string) ($a['description'] ?? '');
            $entity = isset($a['entity']) ? (string) $a['entity'] : null;
            $route = (string) ($a['route'] ?? '');

            $kw = [];
            foreach (['keywords', 'synonyms', 'tags'] as $k) {
                if (isset($a[$k]) && is_array($a[$k])) {
                    foreach ($a[$k] as $v) {
                        if (is_string($v) && trim($v) !== '') {
                            $kw[] = trim($v);
                        }
                    }
                }
            }
            $kw = array_values(array_unique($kw));

            $params = [
                'expected' => $a['parameters'] ?? [],
                'provided' => [],
            ];

            $item = new UiActionCatalogItem(
                $actionId,
                $display,
                $desc,
                $entity !== '' ? $entity : null,
                $route,
                $kw,
                $params
            );

            $items[] = $item;
            $byId[$actionId] = $item;
        }

        return new self($items, $byId);
    }
}

