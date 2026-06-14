<?php

namespace console\controllers;

use common\components\Platform\Core\DataAccess\Validation\DataAccessCatalogCheckService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Valida coherencia del catálogo DataAccess (YAML ↔ modelos ↔ ui_json ↔ BD).
 */
class DataAccessCatalogController extends Controller
{
    public function actionCheck(): int
    {
        $errors = (new DataAccessCatalogCheckService())->run();
        if ($errors === []) {
            $this->stdout("DataAccess catalog OK\n");

            return ExitCode::OK;
        }

        $this->stderr("DataAccess catalog: " . count($errors) . " error(es)\n");
        foreach ($errors as $err) {
            $this->stderr(' - ' . $err . "\n");
        }

        return ExitCode::UNSPECIFIED_ERROR;
    }
}
