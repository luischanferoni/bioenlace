<?php

namespace admin\controllers;

use Yii;
use yii\web\Controller;

/**
 * Retirado del menú admin: el catálogo unificado está en {@see PermissionCatalogController}.
 */
class DataAccessCatalogController extends Controller
{
    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceAdminAccessControl::class,
            ],
        ];
    }

    public function actionIndex()
    {
        Yii::$app->session->setFlash(
            'info',
            'El catálogo DataAccess se consulta en Catálogo de permisos (intents y detalle YAML).'
        );

        return $this->redirect(['permission-catalog/index']);
    }
}
