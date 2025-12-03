<?php

namespace backend\controllers;

use Yii;
use yii\web\NotFoundHttpException;

use webvimark\components\AdminDefaultController;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\search\UserSearch;

use common\models\Persona;

/**
 * UserController implements the CRUD actions for User model.
 */
class UserController extends \webvimark\modules\UserManagement\controllers\UserController
{
    public function behaviors() {
        //control de acceso mediante la extensión
        return [
            'ghost-access' => [
                'class' => 'webvimark\modules\UserManagement\components\GhostAccessControl',
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

                    return $this->redirect(['/user-management/user/view', 'id' => $model->id]);
                }                        
        }

        return $this->render('@backend/views/user-management/user/create', ['model' => $model]);
    }

    public function actionImpersonate($id)
    {
        //echo Yii::getAlias('@frontend/runtime');die;
        file_put_contents(Yii::getAlias('@frontend').'/runtime/impersonation/a.txt', $id, LOCK_EX);

        $url = Yii::$app->urlManager->createAbsoluteUrl(['site/impersonate']);
        $url = str_replace("/admin/", "/", $url);
    	return $this->redirect($url);
    }  
}