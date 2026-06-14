<?php

namespace frontend\controllers;

use common\models\Person\Persona;
use common\models\User;
use frontend\filters\SisseActionFilter;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Alta/actualización de usuario desde flujos frontend (personas / profesional-salud).
 */
class UserController extends \backend\controllers\UserAccountController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => SisseActionFilter::class,
                'only' => ['crear'],
                'filtrosExtra' => [
                    SisseActionFilter::FILTRO_PACIENTE,
                    SisseActionFilter::FILTRO_CONTEXTO_PROFESIONAL,
                ],
            ],
        ];
    }

    /**
     * @no_intent_catalog
     */
    public function actionCrear()
    {
        $model = new User(['scenario' => 'newUser']);

        $personaRaw = Yii::$app->session->get('persona');
        $persona = is_string($personaRaw) ? @unserialize($personaRaw) : null;
        if ($persona !== null && isset($persona->nombre, $persona->apellido)) {
            $model->username = strtolower($persona->nombre . '' . $persona->apellido);
        }

        if (Yii::$app->request->isPost
            && $model->load(Yii::$app->request->post())
            && $model->save()
            && $persona !== null
        ) {
            $persona->scenario = 'scenarioactualizaruser';
            $persona->id_user = $model->id;
            $persona->save();
            Yii::$app->session->set('persona', serialize($persona));

            return $this->redirect(['profesional-salud/create']);
        }

        return $this->render('@frontend/views/user-management/user/create', ['model' => $model]);
    }

    /**
     * @no_intent_catalog
     */
    public function actionUpdate($id)
    {
        $personaRaw = Yii::$app->session->get('persona');
        $persona = is_string($personaRaw) ? @unserialize($personaRaw) : null;

        $model = User::findOne((int) $id);
        if ($model === null) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        if ($model->load(Yii::$app->request->post()) && $model->save() && $persona !== null) {
            return $this->redirect(['personas/view', 'id' => $persona->id_persona]);
        }

        return $this->render('@frontend/views/user-management/user/update', ['model' => $model]);
    }
}
