<?php

namespace console\controllers;

use common\components\FlowManifest\FlowManifestCompiler;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Compila manifiestos de flow (`ui_type=flow`) desde YAML de SubIntentEngine.
 */
class FlowManifestController extends Controller
{
    /** @var bool Solo validar que los JSON en disco coinciden con el compilador (sin escribir). */
    public $check = false;

    /**
     * @return int
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['check']);
    }

    /**
     * @return array<string, string>
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), ['c' => 'check']);
    }

    /**
     * Lee `schemas/intents/*.yaml` y escribe (o valida) `frontend/modules/api/v1/views/json/<entidad>/<accion>.json`.
     *
     * @return int
     */
    public function actionCompile()
    {
        $code = FlowManifestCompiler::run((bool) $this->check);

        return $code === 0 ? ExitCode::OK : ExitCode::DATAERR;
    }
}
