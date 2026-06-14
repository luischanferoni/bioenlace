<?php

namespace common\components\Core\Permission;

use common\models\User;
use yii\bootstrap5\Nav;

/**
 * Menú con ítems filtrados por {@see User::canRoute()}.
 */
class BioenlaceGhostNav extends Nav
{
    public function init()
    {
        parent::init();
        $this->ensureVisibility($this->items);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function ensureVisibility(array &$items): bool
    {
        $allVisible = false;

        foreach ($items as &$item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['url']) && !isset($item['visible']) && !in_array($item['url'], ['', '#'], true)) {
                $item['visible'] = User::canRoute($item['url']);
            }

            if (isset($item['items']) && is_array($item['items'])) {
                if (!$this->ensureVisibility($item['items']) && !isset($item['visible'])) {
                    $item['visible'] = false;
                }
            }

            if (isset($item['label']) && (!isset($item['visible']) || $item['visible'] === true)) {
                $allVisible = true;
            }
        }

        return $allVisible;
    }
}
