<?php

namespace frontend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use common\models\busquedas\CirugiaBusqueda;
use common\models\busquedas\QuirofanoSalaBusqueda;
use common\components\Quirofano\UserEfectorAccess;
use frontend\filters\QuirofanoEfectorAccessFilter;

/**
 * Vistas mínimas de agenda quirúrgica: listados en servidor; altas/edición vía API v1 (JWT en sesión).
 */
class QuirofanoController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => ['@']],
                ],
            ],
            'quirofanoEfector' => [
                'class' => QuirofanoEfectorAccessFilter::class,
            ],
        ];
    }

    public function actionIndex()
    {
        $searchModel = new CirugiaBusqueda();
        $idEfector = (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector());
        $searchModel->id_efector = $idEfector ?: null;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'idEfector' => $idEfector,
        ]);
    }

    public function actionSalas()
    {
        $searchModel = new QuirofanoSalaBusqueda();
        $idEfector = (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector());
        $searchModel->id_efector = $idEfector ?: null;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('salas', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'idEfector' => $idEfector,
        ]);
    }

    public function actionCreateSala()
    {
        $idEfector = (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector());
        UserEfectorAccess::requireEfectorAccess($idEfector);

        return $this->render('create_sala', ['idEfector' => $idEfector]);
    }

    public function actionUpdateSala($id)
    {
        return $this->render('update_sala', ['id' => (int) $id]);
    }

    public function actionCreateCirugia()
    {
        $idEfector = (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector());
        UserEfectorAccess::requireEfectorAccess($idEfector);

        return $this->render('create_cirugia', ['idEfector' => $idEfector]);
    }

    public function actionUpdateCirugia($id)
    {
        return $this->render('update_cirugia', ['id' => (int) $id]);
    }
}
