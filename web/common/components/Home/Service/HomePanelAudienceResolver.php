<?php

namespace common\components\Home\Service;

use Yii;

/**
 * Resuelve audiencia del panel: staff (sesión operativa), patient o fallback.
 */
final class HomePanelAudienceResolver
{
    public const STAFF = 'staff';
    public const PATIENT = 'patient';
    public const FALLBACK = 'fallback';

    public function resolve(): string
    {
        $encounterClass = Yii::$app->user->getEncounterClass();
        if ($encounterClass !== null && $encounterClass !== '') {
            return self::STAFF;
        }

        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona > 0) {
            return self::PATIENT;
        }

        return self::FALLBACK;
    }
}
