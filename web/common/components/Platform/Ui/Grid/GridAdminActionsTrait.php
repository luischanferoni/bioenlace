<?php

namespace common\components\Platform\Ui\Grid;

use Yii;
use yii\web\Cookie;

/**
 * Acciones de grid heredadas de webvimark AdminDefaultController (page size, bulk).
 */
trait GridAdminActionsTrait
{
    public function actionBulkActivate($attribute = 'status'): void
    {
        $selection = Yii::$app->request->post('selection', []);
        if ($selection === []) {
            return;
        }

        $modelClass = $this->gridModelClass();
        $modelClass::updateAll([$attribute => 1], ['id' => $selection]);
    }

    public function actionBulkDeactivate($attribute = 'status'): void
    {
        $selection = Yii::$app->request->post('selection', []);
        if ($selection === []) {
            return;
        }

        $modelClass = $this->gridModelClass();
        $modelClass::updateAll([$attribute => 0], ['id' => $selection]);
    }

    public function actionBulkDelete(): void
    {
        $selection = Yii::$app->request->post('selection', []);
        if ($selection === []) {
            return;
        }

        $modelClass = $this->gridModelClass();
        foreach ($selection as $id) {
            $model = $modelClass::findOne($id);
            if ($model !== null) {
                $model->delete();
            }
        }
    }

    public function actionGridPageSize(): void
    {
        $pageSize = Yii::$app->request->post('grid-page-size');
        if ($pageSize === null || $pageSize === '') {
            return;
        }

        Yii::$app->response->cookies->add(new Cookie([
            'name' => '_grid_page_size',
            'value' => (string) $pageSize,
            'expire' => time() + 86400 * 365,
        ]));
    }

    /**
     * @return class-string<\yii\db\ActiveRecord>
     */
    abstract protected function gridModelClass(): string;
}
