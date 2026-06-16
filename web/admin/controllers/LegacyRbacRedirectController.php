<?php

namespace admin\controllers;

use Yii;
use yii\web\Controller;

/**
 * Redirige URLs legacy de admin RBAC (permisos, roles, grupos) al catálogo unificado.
 * Las vistas webvimark asociadas fueron eliminadas; solo aplica redirect.
 */
class LegacyRbacRedirectController extends Controller
{
    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceAdminAccessControl::class,
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
