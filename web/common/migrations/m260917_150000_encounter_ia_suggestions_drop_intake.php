<?php

use yii\db\Migration;

/**
 * - Renombra motivos_ia_insights_json → ia_clinical_suggestions (sugerencias IA, no motivo).
 * - Elimina motivos_intake_json (cuestionario muerto; motivo canónico = Condition CC).
 */
class m260917_150000_encounter_ia_suggestions_drop_intake extends Migration
{
    public function safeUp()
    {
        $table = '{{%encounter}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        if (isset($schema->columns['motivos_ia_insights_json'])
            && !isset($schema->columns['ia_clinical_suggestions'])
        ) {
            $this->renameColumn($table, 'motivos_ia_insights_json', 'ia_clinical_suggestions');
        }

        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema !== null && isset($schema->columns['motivos_intake_json'])) {
            $this->dropColumn($table, 'motivos_intake_json');
        }
    }

    public function safeDown()
    {
        $table = '{{%encounter}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        if (!isset($schema->columns['motivos_intake_json'])) {
            $this->addColumn($table, 'motivos_intake_json', $this->text()->null());
        }

        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema !== null
            && isset($schema->columns['ia_clinical_suggestions'])
            && !isset($schema->columns['motivos_ia_insights_json'])
        ) {
            $this->renameColumn($table, 'ia_clinical_suggestions', 'motivos_ia_insights_json');
        }
    }
}
