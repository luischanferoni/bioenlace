<?php

use yii\db\Migration;

/**
 * Sesiones efímeras del sandbox demo (PES temporal + seed por visitante).
 *
 * @see web/docs/plans/demo-sandbox-institucional/design.md
 */
class m260803_150000_demo_sandbox_session extends Migration
{
    private string $accessTable = '{{%demo_sandbox_access}}';

    private string $sessionTable = '{{%demo_sandbox_session}}';

    public function safeUp(): void
    {
        $accessSchema = $this->db->schema->getTableSchema($this->accessTable, true);
        if ($accessSchema !== null) {
            if (isset($accessSchema->columns['username']) && !$accessSchema->columns['username']->allowNull) {
                $this->alterColumn($this->accessTable, 'username', $this->string(64)->null());
            }
            if (isset($accessSchema->columns['id_user']) && !$accessSchema->columns['id_user']->allowNull) {
                $this->alterColumn($this->accessTable, 'id_user', $this->integer()->null());
            }
        }

        if ($this->db->schema->getTableSchema($this->sessionTable, true) !== null) {
            return;
        }

        $this->createTable($this->sessionTable, [
            'id' => $this->primaryKey(),
            'id_access' => $this->integer()->null(),
            'role' => $this->string(32)->notNull(),
            'id_efector' => $this->integer()->notNull(),
            'id_user' => $this->integer()->notNull(),
            'id_persona' => $this->integer()->notNull(),
            'id_pes' => $this->integer()->notNull(),
            'id_servicio' => $this->integer()->notNull(),
            'username' => $this->string(64)->notNull(),
            'seed_payload_json' => $this->text()->null(),
            'expires_at' => $this->dateTime()->notNull(),
            'purged_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_demo_session_user', $this->sessionTable, 'id_user');
        $this->createIndex('ix_demo_session_expires', $this->sessionTable, ['expires_at', 'purged_at']);
        $this->createIndex('ix_demo_session_access', $this->sessionTable, 'id_access');
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema($this->sessionTable, true) !== null) {
            $this->dropTable($this->sessionTable);
        }
        // No revertimos nullability de demo_sandbox_access (compatible hacia atrás).
    }
}
