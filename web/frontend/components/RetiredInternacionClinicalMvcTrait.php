<?php

namespace frontend\components;

use yii\web\GoneHttpException;

/**
 * MVC clínico de internación retirado: captura vía timeline + encounter (IMP).
 */
trait RetiredInternacionClinicalMvcTrait
{
    /**
     * @param \yii\base\Action $action
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        throw new GoneHttpException(
            'Captura clínica de internación migrada al timeline (encounter IMP). '
            . 'Use paciente/historia con parent=INTERNACION o el botón Atender en ronda/mapa.'
        );
    }
}
