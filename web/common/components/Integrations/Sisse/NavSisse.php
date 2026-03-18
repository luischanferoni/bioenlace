<?php

declare(strict_types=1);

namespace common\components\Integrations\Sisse;

use Yii;
use yii\base\Widget;
use common\models\User;

class NavSisse extends Widget
{
    public $items = [];
    public $options = ['class' => 'list-unstyled ps-0'];
    public $encodeLabels = false;

    public function init()
    {
        parent::init();
        $this->ensureVisibility($this->items);
    }

    public function run()
    {
        return $this->renderItems($this->items);
    }

    protected function renderItems($items): string
    {
        if (empty($items)) {
            return '';
        }

        $html = '<ul class="list-unstyled ps-0">';

        foreach ($items as $item) {
            if (isset($item['visible']) && $item['visible'] === false) {
                continue;
            }

            $html .= $this->renderItem($item);
        }

        $html .= '</ul>';

        return $html;
    }

    protected function renderItem($item): string
    {
        if (is_string($item)) {
            return $item;
        }

        if (!isset($item['label'])) {
            return '';
        }

        $label = $this->encodeLabels ? htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') : $item['label'];
        $url = $this->normalizeUrl($item['url'] ?? '#');
        $icon = $item['icon'] ?? null;
        $items = $item['items'] ?? null;
        $visible = $item['visible'] ?? true;
        $active = $item['active'] ?? $this->isItemActive($item);

        if ($visible === false) {
            return '';
        }

        // Sub-items
        if (is_array($items) && !empty($items)) {
            $subHtml = '';
            foreach ($items as $subItem) {
                if (isset($subItem['visible']) && $subItem['visible'] === false) {
                    continue;
                }
                $subHtml .= $this->renderItem($subItem);
            }
            if ($subHtml === '') {
                return '';
            }

            $id = 'nav-sisse-' . md5($label . $url);
            $expanded = $active ? 'true' : 'false';
            $collapsed = $active ? '' : ' collapsed';
            $show = $active ? ' show' : '';

            $html = '<li class="mb-1">';
            $html .= '<button class="btn btn-toggle align-items-center rounded' . $collapsed . '" data-bs-toggle="collapse" data-bs-target="#' . $id . '" aria-expanded="' . $expanded . '">';
            if ($icon) {
                $html .= '<span class="me-2">' . $icon . '</span>';
            }
            $html .= $label;
            $html .= '</button>';
            $html .= '<div class="collapse' . $show . '" id="' . $id . '">';
            $html .= '<ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">';
            $html .= $subHtml;
            $html .= '</ul>';
            $html .= '</div>';
            $html .= '</li>';

            return $html;
        }

        // Single item
        if ($url === '#') {
            return '';
        }

        // Permission check
        if (!$this->canAccess($url)) {
            return '';
        }

        $activeClass = $active ? ' active' : '';

        $html = '<li>';
        $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="link-dark rounded' . $activeClass . '">';
        if ($icon) {
            $html .= '<span class="me-2">' . $icon . '</span>';
        }
        $html .= $label;
        $html .= '</a>';
        $html .= '</li>';

        return $html;
    }

    protected function normalizeUrl($url): string
    {
        if (is_array($url)) {
            return Yii::$app->urlManager->createUrl($url);
        }
        return (string)$url;
    }

    protected function isItemActive($item): bool
    {
        $url = $item['url'] ?? null;
        if (empty($url) || $url === '#') {
            return false;
        }

        $currentUrl = Yii::$app->request->url;
        $itemUrl = $this->normalizeUrl($url);

        return $currentUrl === $itemUrl;
    }

    protected function ensureVisibility(&$items): void
    {
        if (!is_array($items)) {
            return;
        }

        foreach ($items as &$item) {
            if (!is_array($item)) {
                continue;
            }

            // Subitems: compute visibility recursively
            if (isset($item['items']) && is_array($item['items'])) {
                $this->ensureVisibility($item['items']);
                $hasVisibleChild = false;
                foreach ($item['items'] as $child) {
                    if (is_array($child) && ($child['visible'] ?? true) !== false) {
                        $hasVisibleChild = true;
                        break;
                    }
                }
                if (!$hasVisibleChild) {
                    $item['visible'] = false;
                }
            } else {
                $url = $item['url'] ?? null;
                if (!empty($url) && $url !== '#') {
                    $item['visible'] = $this->canAccess($this->normalizeUrl($url));
                }
            }
        }
    }

    protected function canAccess(string $url): bool
    {
        // Para rutas absolutas ya formadas, canRoute debería funcionar igual (si el proyecto lo soporta).
        return User::canRoute($url, false);
    }
}

