<?php

namespace common\components\Services\Quirofano;

use Yii;
use yii\web\ForbiddenHttpException;
use common\models\Persona;
use common\models\RrhhEfector;

/**
 * Acceso a datos de quirófano acotado por efectores asignados al usuario (RRHH).
 */
final class UserEfectorAccess
{
    public static function userCanAccessEfector(?int $idEfector): bool
    {
        if ($idEfector === null) {
            return false;
        }
        if (Yii::$app->user->isSuperadmin) {
            return true;
        }
        self::hydrateEfectoresFromDbIfNeeded();
        $efectores = Yii::$app->user->getEfectores();
        if (!is_array($efectores)) {
            return false;
        }
        foreach ($efectores as $key => $row) {
            if (is_array($row) && isset($row['id_efector']) && (int) $row['id_efector'] === (int) $idEfector) {
                return true;
            }
            if (!is_array($row) && (int) $key === (int) $idEfector) {
                return true;
            }
        }
        return false;
    }

    public static function hydrateEfectoresFromDbIfNeeded(): void
    {
        $efectores = Yii::$app->user->getEfectores();
        if (!empty($efectores)) {
            return;
        }
        $idPersona = Yii::$app->user->getIdPersona();
        if (!$idPersona && Yii::$app->user->identity) {
            $persona = Persona::findOne(['id_user' => Yii::$app->user->identity->id]);
            if ($persona) {
                $idPersona = $persona->id_persona;
            }
        }
        if ($idPersona) {
            $list = RrhhEfector::getEfectores($idPersona);
            if (!empty($list)) {
                Yii::$app->user->setEfectores($list);
            }
        }
    }

    /**
     * @throws ForbiddenHttpException
     */
    public static function requireEfectorAccess(?int $idEfector): void
    {
        if (!self::userCanAccessEfector($idEfector)) {
            throw new ForbiddenHttpException('No tiene permiso para este efector.');
        }
    }
}
