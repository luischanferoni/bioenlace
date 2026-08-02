<?php

use yii\db\Migration;

/**
 * Códigos de un solo uso para acceso demo sandbox desde el sitio institucional.
 *
 * @see web/docs/plans/demo-sandbox-institucional/design.md
 */
class m260802_150000_demo_sandbox_access extends Migration
{
    private string $table = '{{%demo_sandbox_access}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema($this->table, true) !== null) {
            return;
        }

        $this->createTable($this->table, [
            'id' => $this->primaryKey(),
            'code_hash' => $this->string(64)->notNull(),
            'role' => $this->string(32)->notNull(),
            'username' => $this->string(64)->notNull(),
            'id_user' => $this->integer()->notNull(),
            'email' => $this->string(255)->null(),
            'ip' => $this->string(45)->null(),
            'user_agent' => $this->string(512)->null(),
            'expires_at' => $this->dateTime()->notNull(),
            'used_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_demo_sandbox_code_hash', $this->table, 'code_hash', true);
        $this->createIndex('ix_demo_sandbox_ip_created', $this->table, ['ip', 'created_at']);
        $this->createIndex('ix_demo_sandbox_expires', $this->table, ['expires_at', 'used_at']);
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema($this->table, true) !== null) {
            $this->dropTable($this->table);
        }
    }
}
