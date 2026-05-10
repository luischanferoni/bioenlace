<?php

namespace frontend\filters;

use Yii;
use yii\base\Action;
use yii\base\ActionFilter;
use yii\web\ForbiddenHttpException;

/**
 * SisseActionFilter implements a layer of access to the controller actions.
 *
 * It is an action filter that can be added to a controller and handles the `beforeAction` event.
 *
 * To use SisseActionFilter, declare it in the `behaviors()` method of your controller class.
 * In the following example the filter will be applied to the `index` action and
 * the Last-Modified header will contain the date of the last update to the user table in the database.
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => SisseActionFilter::className(),
 *             'only' => ['index'],
 *             'allowed' => function ($params) {
 *                 $q = new \yii\db\Query();
 *                 return $q->from('user')->max('updated_at');
 *             },
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Luis Chanferoni
 * @since 1.0
 */

class SisseActionFilter extends ActionFilter
{
    /**
     * constantes que indican que variables de session requiere el action tener
     */
    const FILTRO_PACIENTE = 'FILTRO_PACIENTE';
    /** Requiere id PES / contexto profesional en sesión (sesión operativa). */
    const FILTRO_CONTEXTO_PROFESIONAL = 'FILTRO_CONTEXTO_PROFESIONAL';
    const FILTRO_CONSULTA = 'FILTRO_CONSULTA';

    /**
     * @var callable a PHP callback that returns true or false.
     * The callback's signature should be:
     *
     * ```php
     * function ($params)
     * ```
     *
     * where `$action` is the [[Action]] object that this filter is currently handling;
     * `$params` takes the value of [[params]]. The callback should return a boolean.
     *
     */
    public $allowed;

    public $filtrosExtra;

    /**
     * @var string the message to be displayed when request isn't allowed
     */
    public $errorMessage = 'No esta permitido realizar esta acción por el momento';

    /**
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * You may override this method to do last-minute preparation for the action.
     * @param Action $action the action to be executed.
     * @return bool whether the action should continue to be executed.
     * @throws ForbiddenHttpException when for every reason the action is not allowed.
     */
    public function beforeAction($action)
    {
        $request = Yii::$app->getRequest();

        if (in_array(self::FILTRO_PACIENTE, $this->filtrosExtra)) {
            if (!Yii::$app->session['persona']) {
                throw new ForbiddenHttpException("Ocurrió un error con el paciente seleccionado");
            }
        }

        if (in_array(self::FILTRO_CONTEXTO_PROFESIONAL, $this->filtrosExtra)) {
            $idPesSesion = Yii::$app->user->getIdProfesionalEfectorServicio();
            $idPes = Yii::$app->user->getIdProfesionalEfectorServicio();
            $tieneContextoProfesional = ($idPesSesion !== null && $idPesSesion !== '')
                || ($idPes !== null && $idPes !== '');
            if (!$tieneContextoProfesional) {
                throw new ForbiddenHttpException(
                    'Falta contexto profesional en sesión. Debe tener asignación en un efector (PES) o fijar sesión operativa.'
                );
            }
        }

        if (in_array(self::FILTRO_CONSULTA, $this->filtrosExtra)) {
            if (!Yii::$app->request->get('id_consulta')) {
                throw new ForbiddenHttpException(
                    "Error al intentar obtener el parámetro id de consulta");
            }
        }        

        if ($this->allowed) {
            $allowed = call_user_func($this->allowed);
            if ($allowed) {
                return true;
            }
        } else {
            return true;
        }

        throw new ForbiddenHttpException($this->errorMessage);
    }

}
