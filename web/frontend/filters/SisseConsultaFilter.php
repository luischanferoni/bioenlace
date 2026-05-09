<?php

namespace frontend\filters;

use Yii;
use yii\base\Action;
use yii\base\ActionFilter;
use yii\web\ForbiddenHttpException;

use common\models\Consulta;
use common\models\Turno;
use common\models\ConsultasConfiguracion;
use common\components\Services\ProfesionalEfectorServicio\ProfesionalContextResolver;

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

class SisseConsultaFilter extends ActionFilter
{
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
        $idConsulta = $request->get('id_consulta');
        $idServicioRrhh = $request->get('id_servicio');
        $encounterClass = $request->get('encounter_class');

       /* $session = Yii::$app->getSession();
        $encounter_class = $session->get('encounterClass');
        $parent_id = $request->get('parent_id');

        $esTurno = Turno::findOne($parent_id);

        //Aqui controlamos que si la consulta viene por un turno, el contexto del profesional sea en un consultorio.
        if (isset($esTurno) && ($encounter_class != 'AMB')){
            return false;
        } */


        // Al tener el id_consulta, ya tengo toda la informacion que necesito
        if ($idConsulta !== '' && $idConsulta !== null) {
            $this->modelConsulta = Consulta::findOne($idConsulta);
            list($urlAnterior, $urlActual, $urlSiguiente) = ConsultasConfiguracion::getUrlPorIdConfiguracion($this->modelConsulta->id_configuracion, $this->modelConsulta->paso_completado + 1);
            
            return [$urlAnterior, $urlActual, $urlSiguiente];
        }

        $this->modelConsulta = new Consulta();

        // Si no recibimos el servicio del rrhh, tenemos que deducirlo
        if ($idServicioRrhh == '' && $idServicioRrhh !== null) {
            $idRrhh = ProfesionalContextResolver::resolveRrhhIdFromSessionOrPes();
            $rrhh = $idRrhh > 0 ? \common\models\RrhhEfector::findOne($idRrhh) : null;
            if ($rrhh === null) {
                throw new ForbiddenHttpException('No se pudo determinar el recurso humano (RRHH/PES) para deducir el servicio.');
            }
            $servicios = \Yii\helpers\ArrayHelper::map($rrhh->rrhhServicio, 'id_servicio', 'servicio.item_name');
            $idServicioRrhh = array_keys($servicios)[0];

            foreach($servicios as $key => $servicio) {
                if ($servicio == 'Medico') {
                    $idServicioRrhh = $key;
                    break;
                }
            }
        }

        // Si no viene encounter_class, usar el de sesión (o fallback AMB).
        if ($encounterClass === null || $encounterClass === '') {
            $encounterClass = Yii::$app->user->getEncounterClass() ?: Consulta::ENCOUNTER_CLASS_AMB;
        }

        list($urlAnterior, $urlActual, $urlSiguiente) = ConsultasConfiguracion::getUrlPorServicioYEncounterClass($idServicioRrhh, $encounterClass);

        $this->urlAnterior = $urlAnterior;
        $this->urlActual = $urlActual;
        $this->urlSiguiente = $urlSiguiente;
    }

}
