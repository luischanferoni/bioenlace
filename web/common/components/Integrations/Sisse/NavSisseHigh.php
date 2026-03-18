<?php

declare(strict_types=1);

namespace common\components\Integrations\Sisse;

use Exception;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\bootstrap5\Nav;
use yii\helpers\Html;

class NavSisseHigh extends Nav
{
    public function init()
    {
        parent::init();

        Html::removeCssClass($this->options, ['widget' => 'nav']);
    }

    public function renderItem($item): string
    {
        if (is_string($item)) {
            return $item;
        }
        if (!isset($item['label'])) {
            throw new InvalidConfigException("The 'label' option is required.");
        }
        $encodeLabel = $item['encode'] ?? $this->encodeLabels;
        $label = $encodeLabel ? Html::encode($item['label']) : $item['label'];
        $options = ArrayHelper::getValue($item, 'options', []);
        $items = ArrayHelper::getValue($item, 'items');
        $url = ArrayHelper::getValue($item, 'url', '#');
        $linkOptions = ArrayHelper::getValue($item, 'linkOptions', []);
        $disabled = ArrayHelper::getValue($item, 'disabled', false);
        $active = $this->isItemActive($item);

        if (empty($items)) {
            $items = '';
            Html::addCssClass($options, ['widget' => 'nav-item']);
            Html::addCssClass($linkOptions, ['widget' => 'nav-link']);
        } else {
            $linkOptions['data']['bs-toggle'] = 'dropdown';
            $linkOptions['role'] = 'button';
            $linkOptions['aria']['expanded'] = 'false';
            Html::addCssClass($options, ['widget' => 'nav-item dropdown']);
            Html::addCssClass($linkOptions, ['widget' => 'nav-link']);
            if (is_array($items)) {
                $items = $this->isChildActive($items, $active);
                $items = $this->renderDropdown($items, $item);
            }
        }

        if ($disabled) {
            Html::addCssClass($options, ['widget' => 'disabled']);
            $linkOptions['tabindex'] = '-1';
            $linkOptions['aria']['disabled'] = 'true';
            Html::addCssClass($linkOptions, ['widget' => 'disabled']);
        }

        if ($active) {
            Html::addCssClass($options, ['widget' => 'active']);
            Html::addCssClass($linkOptions, ['widget' => 'active']);
        }

        $link = Html::a($label, $url, $linkOptions);

        return Html::tag('li', $link . $items, $options);
    }
}

