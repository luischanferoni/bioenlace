<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;

/**
 * Retirado del menú admin: campos editables se gestionan fuera del panel (migraciones/CLI).
 */
class DataAccessAttributeFieldController extends Controller
{
    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceBackendAccessControl::class,
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->redirectWithNotice();
    }

    public function actionView($id)
    {
        return $this->redirectWithNotice();
    }

    public function actionCreate()
    {
        return $this->redirectWithNotice();
    }

    public function actionUpdate($id)
    {
        return $this->redirectWithNotice();
    }

    public function actionDelete($id)
    {
        return $this->redirectWithNotice();
    }

    private function redirectWithNotice()
    {
        Yii::$app->session->setFlash(
            'info',
            'La administración de campos por grupo YAML no está en el panel; use migraciones o consola si aplica.'
        );

        return $this->redirect(['permission-catalog/index']);
    }
}
