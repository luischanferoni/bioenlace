<?php

use yii\db\Migration;

/**
 * Persiste mode (ephemeral|shared_account) en el código demo para no depender de params al consumir.
 */
class m260803_160000_demo_sandbox_access_mode extends Migration
{
    private string $table = '{{%demo_sandbox_access}}';

    public function safeUp(): void
    {
        $schema = $this->db->schema->getTableSchema($this->table, true);
        if ($schema === null) {
            return;
        }
        if (!isset($schema->columns['mode'])) {
            $this->addColumn($this->table, 'mode', $this->string(32)->null()->after('role'));
        }
    }

    public function safeDown(): void
    {
        $schema = $this->db->schema->getTableSchema($this->table, true);
        if ($schema !== null && isset($schema->columns['mode'])) {
            $this->dropColumn($this->table, 'mode');
        }
    }
}
