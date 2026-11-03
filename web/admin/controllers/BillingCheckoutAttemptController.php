<?php

namespace admin\controllers;

use common\models\Organization\BillingCheckoutAttempt;
use common\models\Organization\BillingCheckoutAttemptBusqueda;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Admin: checkouts de suscripción del alta de espacios de atención (solo lectura).
 */
class BillingCheckoutAttemptController extends Controller
{
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => \common\components\Platform\Core\Permission\BioenlaceAdminAccessControl::class,
            ],
        ];
    }

    public function actionIndex()
    {
        $searchModel = new BillingCheckoutAttemptBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    private function findModel($id): BillingCheckoutAttempt
    {
        $model = BillingCheckoutAttempt::findOne(['id' => (int) $id, 'deleted_at' => null]);
        if ($model === null) {
            throw new NotFoundHttpException('Checkout no encontrado.');
        }

        return $model;
    }
}
