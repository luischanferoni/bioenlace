<?php

namespace frontend\filters;

use Yii;
use yii\base\ActionFilter;
use yii\web\ForbiddenHttpException;
use common\components\Quirofano\UserEfectorAccess as QuirofanoUserEfectorAccess;

/**
 * Exige id_efector en GET (o id_efector de sesión) y que el usuario tenga vínculo RRHH con ese efector.
 */
class QuirofanoEfectorAccessFilter extends ActionFilter
{
    public function beforeAction($action)
    {
        if (Yii::$app->user->isSuperadmin) {
            return true;
        }
        $idEfector = (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector());
        if (!$idEfector) {
            throw new ForbiddenHttpException('Indique id_efector (o seleccione efector en sesión).');
        }
        QuirofanoUserEfectorAccess::requireEfectorAccess($idEfector);
        return true;
    }
}
