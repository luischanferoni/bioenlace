<?php

namespace console\controllers;

use common\components\Core\Permission\Validation\CatalogIntegrityService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Integridad del catálogo de permisos (intents + data-access-config + pasos open_ui).
 */
class CatalogIntegrityController extends Controller
{
    public function actionCheck(): int
    {
        $result = (new CatalogIntegrityService())->run();
        $errors = $result['errors'];
        $warnings = $result['warnings'];
        $summary = $result['summary'];

        $this->stdout(sprintf(
            "Catálogo permisos: %d intent(s), %d atributo(s), %d paso(s) open_ui\n",
            (int) ($summary['intents'] ?? 0),
            (int) ($summary['attributes'] ?? 0),
            (int) ($summary['flow_steps'] ?? 0)
        ));

        if ($warnings !== []) {
            $this->stdout("\nAdvertencias (" . count($warnings) . "):\n");
            foreach ($warnings as $w) {
                $this->stdout(' [warn] ' . $w . "\n");
            }
        }

        if ($errors === []) {
            $this->stdout("\nIntegridad OK (0 errores)\n");

            return ExitCode::OK;
        }

        $this->stderr("\nErrores (" . count($errors) . "):\n");
        foreach ($errors as $err) {
            $this->stderr(' [error] ' . $err . "\n");
        }

        return ExitCode::UNSPECIFIED_ERROR;
    }
}
