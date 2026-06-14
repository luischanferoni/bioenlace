<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;

/**
 * Redirige el admin RBAC legacy de webvimark (permisos, roles, grupos) al catálogo unificado.
 */
class LegacyRbacRedirectController extends Controller
{
    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceBackendAccessControl::class,
            ],
        ];
    }

    /**
     * @param \yii\base\Action $action
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Yii::$app->session->setFlash(
            'info',
            'La administración de permisos y roles se realiza en Catálogo de permisos.'
        );
        $this->redirect(['/permission-catalog/index']);

        return false;
    }
}
