<?php

namespace common\components\Platform\Ui\Grid;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/**
 * Selector de tamaño de página para GridView + Pjax (reemplazo webvimark GridPageSize).
 */
class GridPageSize extends Widget
{
    public $domContainer = 'body';

    public $viewFile = 'grid-page-size';

    /** @var string id del contenedor Pjax (sin #) */
    public $pjaxId;

    public $url;

    /** @var array<int, int> */
    public $dropDownOptions;

    public $text;

    public $enableClearFilters = true;

    public $gridId;

    /** @var array<string, mixed> */
    public $clientOptions;

    public function init(): void
    {
        parent::init();
        $this->text = $this->text ?: 'Registros por página';
    }

    public static function t(string $category, string $message, array $params = []): string
    {
        $map = [
            'Records per page' => 'Registros por página',
            'Clear filters' => 'Limpiar filtros',
        ];

        return $map[$message] ?? $message;
    }

    public function run(): string
    {
        if (!$this->pjaxId) {
            throw new InvalidConfigException('Missing pjaxId param');
        }

        $this->setDefaultOptions();
        $this->view->registerJs($this->js());

        return $this->render($this->viewFile);
    }

    protected function setDefaultOptions(): void
    {
        $this->pjaxId = '#' . ltrim($this->pjaxId, '#');

        if (!$this->gridId) {
            $this->gridId = substr($this->pjaxId, 0, -5);
        }

        $this->gridId = '#' . ltrim($this->gridId, '#');

        if (!$this->dropDownOptions) {
            $this->dropDownOptions = [5 => 5, 10 => 10, 20 => 20, 50 => 50, 100 => 100, 200 => 200];
        }

        if (!$this->url) {
            $this->url = Url::to(['grid-page-size']);
        }
    }

    protected function js(): string
    {
        $options = ['container' => $this->pjaxId];
        if ($this->clientOptions) {
            $options = ArrayHelper::merge($options, $this->clientOptions);
        }
        $optionsJson = json_encode($options);
        $js = <<<JS
$('{$this->domContainer}').off('change', '[name="grid-page-size"]').on('change', '[name="grid-page-size"]', function () {
    var _t = $(this);
    $.post('{$this->url}', { 'grid-page-size': _t.val() })
        .done(function () {
            $.pjax.reload({$optionsJson});
        });
});
JS;

        return $this->enableClearFilters ? $this->jsWithClearFilters() . $js : $js;
    }

    protected function jsWithClearFilters(): string
    {
        $filterSelectors = $this->gridId . ' .filters input[type="text"], ' . $this->gridId . ' .filters select';
        $clearBtnId = $this->gridId . '-clear-filters-btn';

        return <<<JS
var clearFiltersBtn = $('{$clearBtnId}');
var domContainer = $('{$this->domContainer}');

function showOrHideClearFiltersBtn() {
    var showClearFiltersButton = false;
    $('{$filterSelectors}').each(function () {
        if ($(this).val()) {
            showClearFiltersButton = true;
        }
    });
    clearFiltersBtn.toggle(showClearFiltersButton);
}

showOrHideClearFiltersBtn();

domContainer.off('change', '{$filterSelectors}').on('change', '{$filterSelectors}', function () {
    showOrHideClearFiltersBtn();
});

domContainer.off('click', '{$clearBtnId}').on('click', '{$clearBtnId}', function () {
    var filter;
    $('{$filterSelectors}').each(function () {
        filter = $(this);
        filter.val('');
    });
    filter.trigger('change');
});

JS;
    }
}
