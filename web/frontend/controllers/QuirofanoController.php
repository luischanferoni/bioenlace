<?php

namespace frontend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use common\models\busquedas\CirugiaBusqueda;
use common\models\busquedas\QuirofanoSalaBusqueda;
use common\components\Scheduling\Service\Quirofano\UserEfectorAccess;
use frontend\filters\QuirofanoEfectorAccessFilter;

/**
 * Vistas mínimas de agenda quirúrgica: listado, alta/edición de ítem de agenda (como turnos), salas.
 * La nota o informe clínico del acto es consulta en HC, no esta pantalla.
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

    /**
     * @no_intent_catalog
    */
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

    /**
     * @no_intent_catalog
    */
    public function actionCreateSala()
    {
        $idEfector = (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector());
        UserEfectorAccess::requireEfectorAccess($idEfector);

        return $this->render('create_sala', ['idEfector' => $idEfector]);
    }

    /**
     * @no_intent_catalog
     */
    public function actionCreateCirugia()
    {
        $idEfector = (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector());
        UserEfectorAccess::requireEfectorAccess($idEfector);

        return $this->render('create_cirugia', ['idEfector' => $idEfector]);
    }

    /**
     * @no_intent_catalog
     */
    public function actionSalas()
    {
        $idEfector = (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector());
        UserEfectorAccess::requireEfectorAccess($idEfector);

        $searchModel = new QuirofanoSalaBusqueda();
        $searchModel->id_efector = $idEfector ?: null;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('salas', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'idEfector' => $idEfector,
        ]);
    }

    /**
     * @no_intent_catalog
     */
    public function actionUpdateSala($id)
    {
        $idEfector = (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector());
        UserEfectorAccess::requireEfectorAccess($idEfector);

        return $this->render('update_sala', ['id' => (int) $id]);
    }

    /**
     * @no_intent_catalog
     */
    public function actionUpdateCirugia($id)
    {
        $idEfector = (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector());
        UserEfectorAccess::requireEfectorAccess($idEfector);

        return $this->render('update_cirugia', ['id' => (int) $id]);
    }
}
