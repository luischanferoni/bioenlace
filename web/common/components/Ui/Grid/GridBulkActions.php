<?php

namespace common\components\Ui\Grid;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Url;

/**
 * Acciones masivas en GridView (reemplazo webvimark GridBulkActions).
 */
class GridBulkActions extends Widget
{
    /** @var array<string, string|array<string, string>> */
    public $actions;

    public $gridId;

    public $pjaxId;

    public $okButtonClass = 'btn btn-sm btn-default';

    public $dropDownClass = 'form-control input-sm';

    public $wrapperClass = 'form-inline';

    public $promptText;

    public $confirmationText;

    public function init(): void
    {
        parent::init();
        $this->promptText = $this->promptText ?: '--- Con seleccionados ---';
        $this->confirmationText = $this->confirmationText ?: '¿Eliminar elementos?';
    }

    public static function t(string $category, string $message, array $params = []): string
    {
        $map = [
            '--- With selected ---' => '--- Con seleccionados ---',
            'Delete elements?' => '¿Eliminar elementos?',
            'Activate' => 'Activar',
            'Deactivate' => 'Desactivar',
            'Delete' => 'Eliminar',
        ];

        return $map[$message] ?? $message;
    }

    public function run(): string
    {
        if (!$this->gridId) {
            throw new InvalidConfigException('Missing gridId param');
        }

        $this->setDefaultOptions();
        $this->view->registerJs($this->js());

        return $this->render('grid-bulk-actions');
    }

    protected function setDefaultOptions(): void
    {
        if (!$this->actions) {
            $this->actions = [
                Url::to(['bulk-activate']) => self::t('app', 'Activate'),
                Url::to(['bulk-deactivate']) => self::t('app', 'Deactivate'),
                '----' => [
                    Url::to(['bulk-delete']) => self::t('app', 'Delete'),
                ],
            ];
        }

        if (!$this->pjaxId) {
            $this->pjaxId = $this->gridId . '-pjax';
        }

        $this->gridId = ltrim($this->gridId, '#');
        $this->pjaxId = ltrim($this->pjaxId, '#');
    }

    protected function js(): string
    {
        return <<<JS
$(document).off('change', '[name="grid-bulk-actions"]').on('change', '[name="grid-bulk-actions"]', function () {
    var _t = $(this);
    var okButton = $(_t.data('ok-button'));
    if (_t.val()) {
        okButton.removeClass('disabled');
    } else {
        okButton.addClass('disabled');
    }
});

$(document).off('click', '.grid-bulk-ok-button').on('click', '.grid-bulk-ok-button', function () {
    var _t = $(this);
    var list = $(_t.data('list'));

    if (list.val().indexOf('bulk-delete') >= 0) {
        if (!confirm('{$this->confirmationText}')) {
            return false;
        }
    }

    $.post(list.val(), $(_t.data('grid') + ' [name="selection[]"]').serialize())
        .done(function () {
            _t.addClass('disabled');
            list.val('');
            $.pjax.reload({container: _t.data('pjax')});
        });
});
JS;
    }
}
