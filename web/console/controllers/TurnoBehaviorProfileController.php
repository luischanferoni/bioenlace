<?php

namespace console\controllers;

use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileMaterializerService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Operaciones batch del perfil factual de comportamiento en turnos.
 *
 * Sin backfill: el perfil sólo materializa eventos NATIVE del stream canónico.
 *
 * Uso:
 *   php yii turno-behavior-profile/materialize [--limitPersonas=]
 *   php yii turno-behavior-profile/rebuild [--idPersona=] [--limitPersonas=]
 */
class TurnoBehaviorProfileController extends Controller
{
    public $idPersona;
    public $limitPersonas;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), [
            'idPersona',
            'limitPersonas',
        ]);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'p' => 'idPersona',
        ]);
    }

    public function actionMaterialize(): int
    {
        $svc = new TurnoBehaviorProfileMaterializerService();
        $result = $svc->materializeIncremental(
            $this->limitPersonas !== null && $this->limitPersonas !== '' ? (int) $this->limitPersonas : null
        );
        $this->stdout(sprintf(
            "materialize personas=%d perfiles=%d watermark=%s\n",
            $result['personas'],
            $result['perfiles'],
            $result['watermark'] === null ? 'null' : (string) $result['watermark']
        ));

        return ExitCode::OK;
    }

    public function actionRebuild(): int
    {
        $svc = new TurnoBehaviorProfileMaterializerService();
        $result = $svc->rebuild(
            $this->idPersona !== null && $this->idPersona !== '' ? (int) $this->idPersona : null,
            $this->limitPersonas !== null && $this->limitPersonas !== '' ? (int) $this->limitPersonas : null
        );
        $this->stdout(sprintf(
            "rebuild personas=%d perfiles=%d watermark=%s\n",
            $result['personas'],
            $result['perfiles'],
            $result['watermark'] === null ? 'null' : (string) $result['watermark']
        ));

        return ExitCode::OK;
    }
}
