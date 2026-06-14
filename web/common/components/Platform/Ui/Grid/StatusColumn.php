<?php

namespace common\components\Platform\Ui\Grid;

use Yii;
use yii\base\InvalidConfigException;
use yii\grid\DataColumn;
use yii\helpers\ArrayHelper;

/**
 * Columna con badge de estado y filtro dropdown (reemplazo webvimark StatusColumn).
 */
class StatusColumn extends DataColumn
{
    /** @var list<array{0: int|string, 1: string, 2: string|false}> */
    public $optionsArray = [];

    public $toggleUrl;

    public $pjaxId;

    public $withPjax = true;

    /** @var array<int|string, string|false> */
    protected $_labelClasses = [];

    public function init(): void
    {
        parent::init();

        $this->setDefaultOptions();

        if ($this->toggleUrl) {
            $this->grid->view->registerJs(
                $this->withPjax ? $this->jsWithPjax() : $this->jsWithoutPjax()
            );
        }

        $this->initOptions();
    }

    protected function initOptions(): void
    {
        $this->checkOptionsArray();
        $this->setCellStyleOptions();
        $this->format = 'raw';

        foreach ($this->optionsArray as $option) {
            $this->filter[$option[0]] = $option[1];
            $this->_labelClasses[$option[0]] = $option[2];
        }

        if ($this->value instanceof \Closure) {
            $userFunc = $this->value;
        } else {
            $userFunc = function ($model) {
                return $model->{$this->attribute};
            };
        }

        $this->value = function ($model, $key, $index, $widget) use ($userFunc) {
            $attributeValue = call_user_func($userFunc, $model, $key, $index, $widget);

            if (!isset($widget->_labelClasses[$attributeValue], $widget->filter[$attributeValue])) {
                return $attributeValue;
            }

            $label = $widget->_labelClasses[$attributeValue];
            $value = $widget->filter[$attributeValue];
            $class = ($label === false) ? '' : "badge bg-{$label}";
            $style = ($label === false) ? '' : 'font-size:85%;';
            $data = '';

            if (!empty($this->toggleUrl)) {
                $style .= 'cursor:pointer;';
                preg_match('/=_\w+_/', $this->toggleUrl, $matches);
                $idAttributePlaceholder = ltrim($matches[0] ?? '', '=');
                $idAttribute = trim($idAttributePlaceholder, '_');
                $toggleUrl = str_replace($idAttributePlaceholder, $model->{$idAttribute}, $this->toggleUrl);
                $dataType = empty($this->pjaxId) ? 'grid-toggle' : 'grid-toggle-pjax';
                $data .= "data-type='{$dataType}'";
                $data .= " data-url='{$toggleUrl}'";
            }

            return "<span style='{$style}' {$data} class='{$class}'> {$value} </span>";
        };
    }

    protected function setDefaultOptions(): void
    {
        if ($this->withPjax && !$this->pjaxId) {
            $this->pjaxId = $this->grid->id . '-pjax';
        }
    }

    protected function setCellStyleOptions(): void
    {
        $this->contentOptions = ArrayHelper::merge(
            ['style' => 'text-align:center; width:80px; white-space:nowrap;'],
            $this->contentOptions
        );
    }

    protected function checkOptionsArray(): void
    {
        if (!is_array($this->optionsArray)) {
            throw new InvalidConfigException('Options should be an array');
        }

        if (empty($this->optionsArray)) {
            $this->optionsArray = [
                [0, Yii::t('yii', 'No'), 'warning'],
                [1, Yii::t('yii', 'Yes'), 'success'],
            ];
        }
    }

    protected function jsWithPjax(): string
    {
        return <<<JS
$(document).off('click', "[data-type='grid-toggle-pjax']").on('click', "[data-type='grid-toggle-pjax']", function () {
    $.get($(this).data('url')).success(function () {
        $.pjax.reload({container: '#{$this->pjaxId}'});
    });
});
JS;
    }

    protected function jsWithoutPjax(): string
    {
        return <<<JS
$(document).off('click', "[data-type='grid-toggle']").on('click', "[data-type='grid-toggle']", function () {
    window.location = $(this).data('url');
});
JS;
    }
}
