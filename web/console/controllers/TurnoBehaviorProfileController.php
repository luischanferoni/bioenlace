<?php

namespace console\controllers;

use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileBackfillService;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileMaterializerService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Operaciones batch del perfil factual de comportamiento en turnos (fases 1–2).
 *
 * Uso:
 *   php yii turno-behavior-profile/backfill [--idPersona=] [--limit=] [--offset=]
 *   php yii turno-behavior-profile/materialize [--limitPersonas=]
 *   php yii turno-behavior-profile/rebuild [--idPersona=] [--limitPersonas=]
 */
class TurnoBehaviorProfileController extends Controller
{
    public $idPersona;
    public $limit;
    public $offset = 0;
    public $limitPersonas;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), [
            'idPersona',
            'limit',
            'offset',
            'limitPersonas',
        ]);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'p' => 'idPersona',
            'l' => 'limit',
        ]);
    }

    public function actionBackfill(): int
    {
        $svc = new TurnoBehaviorProfileBackfillService();
        $result = $svc->backfill(
            $this->idPersona !== null && $this->idPersona !== '' ? (int) $this->idPersona : null,
            $this->limit !== null && $this->limit !== '' ? (int) $this->limit : null,
            (int) $this->offset
        );
        $this->stdout(sprintf(
            "backfill processed=%d written=%d skipped=%d\n",
            $result['processed'],
            $result['written'],
            $result['skipped']
        ));

        return ExitCode::OK;
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
