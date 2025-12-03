<?php

namespace frontend\controllers;

use Yii;
use yii\web\NotFoundHttpException;

use webvimark\components\AdminDefaultController;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\search\UserSearch;

use frontend\filters\SisseActionFilter;
use common\models\Persona;

/**
 * UserController implements the CRUD actions for User model.
 */
class UserController extends \webvimark\modules\UserManagement\controllers\UserController
{
    public function behaviors()
    {
        //control de acceso mediante la extensión
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['crear'],
                'filtrosExtra' => [
                    SisseActionFilter::FILTRO_PACIENTE, 
                    SisseActionFilter::FILTRO_RECURSO_HUMANO
                ],
            ],            
        ];
    }

	/**
	 * @return mixed|string|\yii\web\Response
	 */    
    public function actionCrear()
    {
        $model = new User(['scenario'=>'newUser']);

        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);

        $model->username = strtolower($persona->nombre.''.$persona->apellido);

        if (Yii::$app->request->post()) {
            // Recibimos el mail, en caso de ya existir en el sistema
            // asignamos directamente el id_user encontrado
           /* $user = false;
            $username = Yii::$app->request->post('User')['username'];
            if(isset($username) && $username != '') {
                $user = User::find()->where(['username' => $username])->one();
            }*/
            // No existe usuario, lo creo y asocio con persona
            //if (!$user) {
                if ($model->load(Yii::$app->request->post()) && $model->save()) {
                    
                    $persona->scenario = "scenarioactualizaruser";
                    $persona->id_user = $model->id;
                    
                    $persona->save();

                    // pisamos la session para que lleve el id_user
                    $session = Yii::$app->getSession();
                    $session->set('persona', serialize($persona));

                    return $this->redirect(['profesional-salud/create']);
                }
        }

        return $this->render('@frontend/views/user-management/user/create', ['model' => $model]);
    }

	/**
	 * Updates an existing model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 *
	 * @return mixed
	 */
	public function actionUpdate($id)
	{
        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);

		$model = $this->findModel($id);

		if ( $model->load(Yii::$app->request->post()) AND $model->save())
		{
            return $this->redirect(['personas/view', 'id' => $persona->id_persona]);
		}

		return $this->renderIsAjax('@frontend/views/user-management/user/update', compact('model'));
	}
    
	/**
	 * Finds the model based on its primary key value.
	 * If the model is not found, a 404 HTTP exception will be thrown.
	 *
	 * @param mixed $id
	 *
	 * @return ActiveRecord the loaded model
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	protected function findModel($id)
	{
		if ( ($model = User::findOne($id)) !== null )
		{
			return $model;
		}
		else
		{
			throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
		}
	}    
}