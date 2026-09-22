<?php

namespace console\controllers;

use common\components\Domain\Scheduling\BehaviorProfile\Application\Service\TurnoBehaviorStreamCoverageService;
use common\components\Domain\Scheduling\BehaviorProfile\Application\UseCase\MaterializeTurnoBehaviorProfile;
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
 *   php yii turno-behavior-profile/coverage [--since=YYYY-MM-DD]
 */
class TurnoBehaviorProfileController extends Controller
{
    public $idPersona;
    public $limitPersonas;
    public $since;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), [
            'idPersona',
            'limitPersonas',
            'since',
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
        $svc = new MaterializeTurnoBehaviorProfile();
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
        $svc = new MaterializeTurnoBehaviorProfile();
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

    public function actionCoverage(): int
    {
        $svc = new TurnoBehaviorStreamCoverageService();
        $report = $svc->summarize(
            $this->since !== null && $this->since !== '' ? (string) $this->since : null
        );
        $this->stdout(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        return ExitCode::OK;
    }
}
