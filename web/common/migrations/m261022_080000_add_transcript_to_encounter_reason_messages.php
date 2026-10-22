<?php

use yii\db\Migration;

/**
 * Conserva la transcripción local junto al archivo de audio del chat pre-consulta.
 */
class m261022_080000_add_transcript_to_encounter_reason_messages extends Migration
{
    public function safeUp()
    {
        $table = '{{%interaccion_motivos_consulta}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        if (!isset($schema->columns['transcript'])) {
            $this->addColumn($table, 'transcript', $this->text()->null());
        }
        if (!isset($schema->columns['stt_meta_json'])) {
            $this->addColumn($table, 'stt_meta_json', $this->text()->null());
        }
    }

    public function safeDown()
    {
        $table = '{{%interaccion_motivos_consulta}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        if (isset($schema->columns['stt_meta_json'])) {
            $this->dropColumn($table, 'stt_meta_json');
        }
        if (isset($schema->columns['transcript'])) {
            $this->dropColumn($table, 'transcript');
        }
    }
}
