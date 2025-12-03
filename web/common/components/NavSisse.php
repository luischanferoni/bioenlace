<?php

declare(strict_types=1);

namespace common\components;

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
        $items = $item['items'] ?? [];
        $disabled = $item['disabled'] ?? false;
        $active = $this->isItemActive($item);
        
        $html = '<li class="mb-1">';
        
        if (empty($items)) {
            // Item simple sin subitems
            $class = 'link-body-emphasis d-inline-flex text-decoration-none rounded';
            if ($active) {
                $class .= ' active';
            }
            if ($disabled) {
                $class .= ' disabled';
            }
            
            $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="' . $class . '">';
            $html .= $label;
            $html .= '</a>';
        } else {
            // Item con subitems (dropdown)
            $collapseId = $this->generateCollapseId($item);
            $expanded = $active ? 'true' : 'false';
            $collapsedClass = $active ? '' : 'collapsed';
            
            $html .= '<button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 ' . $collapsedClass . '" ';
            $html .= 'data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="' . $expanded . '">';
            $html .= $label;
            $html .= '</button>';
            
            $html .= '<div class="collapse' . ($active ? ' show' : '') . '" id="' . $collapseId . '">';
            $html .= '<ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">';
            
            foreach ($items as $subItem) {
                if (isset($subItem['visible']) && $subItem['visible'] === false) {
                    continue;
                }
                
                $subLabel = $this->encodeLabels ? htmlspecialchars($subItem['label'], ENT_QUOTES, 'UTF-8') : $subItem['label'];
                $subUrl = $this->normalizeUrl($subItem['url'] ?? '#');
                $subActive = $this->isItemActive($subItem);
                $subDisabled = $subItem['disabled'] ?? false;
                
                $subClass = 'link-body-emphasis d-inline-flex text-decoration-none rounded';
                if ($subActive) {
                    $subClass .= ' active';
                }
                if ($subDisabled) {
                    $subClass .= ' disabled';
                }
                
                $html .= '<li><a href="' . htmlspecialchars($subUrl, ENT_QUOTES, 'UTF-8') . '" class="' . $subClass . '">';
                $html .= $subLabel;
                $html .= '</a></li>';
            }
            
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        $html .= '</li>';
        
        return $html;
    }
    
    protected function normalizeUrl($url): string
    {
        if (is_array($url)) {
            // Si es un array, tomar el primer elemento (la ruta)
            return Yii::$app->urlManager->createUrl($url[0] ?? '#');
        }
        
        if (is_string($url)) {
            return Yii::$app->urlManager->createUrl($url);
        }
        
        return '#';
    }
    
    protected function generateCollapseId($item): string
    {
        $label = $item['label'] ?? 'item';
        $label = strip_tags($label);
        $label = preg_replace('/[^a-zA-Z0-9]/', '-', $label);
        $label = strtolower($label);
        $label = trim($label, '-');
        
        return $label . '-collapse';
    }
    
    protected function isItemActive($item): bool
    {
        if (!isset($item['url']) || $item['url'] === '#') {
            return false;
        }
        
        $currentRoute = Yii::$app->controller->route;
        $itemRoute = $this->normalizeUrl($item['url']);
        
        // Remover el slash inicial si existe para comparar rutas
        $currentRoute = ltrim($currentRoute, '/');
        $itemRoute = ltrim($itemRoute, '/');
        
        return $currentRoute === $itemRoute;
    }
    
    protected function ensureVisibility(&$items)
    {
        $allVisible = false;

        foreach ($items as &$item) {
            if (isset($item['url']) && !isset($item['visible']) && !in_array($item['url'], ['', '#'])) {
                $item['visible'] = User::canRoute($item['url']);
            }

            if (isset($item['items'])) {
                // If not children are visible - make invisible this node
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
