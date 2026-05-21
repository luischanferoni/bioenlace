<?php

use common\components\Clinical\EncounterDefinitionWorkflowSanitizer;
use yii\db\Migration;
use yii\db\Query;

/**
 * Quita URLs MVC `consulta-*` / `consultas/*` de encounter_definition.workflow_json.
 * La captura clínica usa API + formulario paciente; se mantienen titulo/relacion/requerido.
 */
class m260521_100008_encounter_definition_sanitize_legacy_workflow_urls extends Migration
{
    public function safeUp()
    {
        $table = '{{%encounter_definition}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            echo "    > encounter_definition no existe; omitir.\n";

            return true;
        }

        $updated = 0;
        $rows = (new Query())
            ->from($table)
            ->select(['id', 'workflow_json'])
            ->where(['deleted_at' => null])
            ->all($this->db);

        foreach ($rows as $row) {
            $result = EncounterDefinitionWorkflowSanitizer::sanitizeWorkflowJson((string) $row['workflow_json']);
            if ($result['error'] !== null) {
                echo "    > id={$row['id']}: omitido ({$result['error']})\n";
                continue;
            }
            if (!$result['changed']) {
                continue;
            }
            $this->db->createCommand()->update(
                $table,
                ['workflow_json' => $result['json']],
                ['id' => $row['id']]
            )->execute();
            $updated++;
            echo '    > id=' . $row['id'] . ' URLs legacy vaciadas: ' . implode(', ', $result['legacy_urls']) . "\n";
        }

        echo "    > encounter_definition: {$updated} fila(s) actualizada(s).\n";

        $legacyCfg = '{{%consultas_configuracion}}';
        if ($this->db->schema->getTableSchema($legacyCfg, true) !== null) {
            $this->sanitizeLegacyConsultasConfiguracionTable($legacyCfg);
        }

        return true;
    }

    public function safeDown()
    {
        echo "    > m260521_100008: safeDown no restaura URLs MVC (irreversible).\n";

        return true;
    }

    private function sanitizeLegacyConsultasConfiguracionTable(string $table): void
    {
        $schema = $this->db->schema->getTableSchema($table, true);
        $col = isset($schema->columns['pasos_json']) ? 'pasos_json' : 'workflow_json';
        $updated = 0;
        $rows = (new Query())->from($table)->select(['id', $col])->all($this->db);
        foreach ($rows as $row) {
            $result = EncounterDefinitionWorkflowSanitizer::sanitizeWorkflowJson((string) $row[$col]);
            if ($result['changed']) {
                $this->db->createCommand()->update($table, [$col => $result['json']], ['id' => $row['id']])->execute();
                $updated++;
            }
        }
        echo "    > consultas_configuracion (legacy): {$updated} fila(s) actualizada(s).\n";
    }
}
