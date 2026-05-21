<?php

namespace console\controllers;

use common\components\Clinical\EncounterDefinitionWorkflowSanitizer;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;
use yii\helpers\Console;

/**
 * Auditoría y saneo de workflow_json en encounter_definition.
 */
class EncounterDefinitionController extends Controller
{
    /** @var bool Solo listar filas con URLs legacy, sin UPDATE */
    public $dryRun = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun']);
    }

    public function optionAliases(): array
    {
        return ['n' => 'dryRun'];
    }

    /**
     * Lista definiciones con URLs MVC legacy en workflow_json.
     */
    public function actionAuditWorkflowUrls(): int
    {
        $table = '{{%encounter_definition}}';
        if (\Yii::$app->db->schema->getTableSchema($table, true) === null) {
            $this->stderr("Tabla encounter_definition no existe.\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $rows = (new Query())
            ->from($table)
            ->select(['id', 'service_id', 'encounter_class', 'workflow_json'])
            ->where(['deleted_at' => null])
            ->all();

        $report = EncounterDefinitionWorkflowSanitizer::auditRows($rows);
        if ($report === []) {
            $this->stdout("OK: ninguna fila con URL legacy consulta*/consultas/*.\n", Console::FG_GREEN);

            return ExitCode::OK;
        }

        $this->stdout(count($report) . " fila(s) con URLs legacy:\n", Console::FG_YELLOW);
        foreach ($report as $item) {
            $this->stdout(sprintf(
                "  id=%d service_id=%d class=%s\n",
                $item['id'],
                $item['service_id'],
                $item['encounter_class']
            ));
            if ($item['error'] !== null) {
                $this->stderr('    JSON: ' . $item['error'] . "\n", Console::FG_RED);
                continue;
            }
            foreach ($item['legacy_urls'] as $url) {
                $this->stdout('    - ' . $url . "\n");
            }
        }

        return ExitCode::OK;
    }

    /**
     * Vacía URLs legacy en workflow_json (o dry-run con --dryRun=1).
     */
    public function actionSanitizeWorkflowUrls(): int
    {
        $table = '{{%encounter_definition}}';
        if (\Yii::$app->db->schema->getTableSchema($table, true) === null) {
            $this->stderr("Tabla encounter_definition no existe.\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $rows = (new Query())
            ->from($table)
            ->select(['id', 'workflow_json'])
            ->where(['deleted_at' => null])
            ->all();

        $wouldUpdate = 0;
        foreach ($rows as $row) {
            $result = EncounterDefinitionWorkflowSanitizer::sanitizeWorkflowJson((string) $row['workflow_json']);
            if ($result['error'] !== null) {
                $this->stderr("id={$row['id']}: {$result['error']}\n", Console::FG_RED);
                continue;
            }
            if (!$result['changed']) {
                continue;
            }
            $wouldUpdate++;
            $this->stdout('id=' . $row['id'] . ': ' . implode(', ', $result['legacy_urls']) . "\n");
            if (!$this->dryRun) {
                \Yii::$app->db->createCommand()->update(
                    $table,
                    ['workflow_json' => $result['json']],
                    ['id' => $row['id']]
                )->execute();
            }
        }

        if ($this->dryRun) {
            $this->stdout("Dry-run: {$wouldUpdate} fila(s) se actualizarían. Ejecutar sin --dryRun=1 para aplicar.\n");
        } else {
            $this->stdout("Actualizadas {$wouldUpdate} fila(s).\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}
