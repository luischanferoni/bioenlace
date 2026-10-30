<?php

use yii\db\Migration;

/**
 * Persistencia idempotente y minimizada de intentos de alta de identidad.
 */
class m261030_000002_identity_signup_attempt extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%identity_signup_attempt}}', true) !== null) {
            return;
        }

        $this->createTable('{{%identity_signup_attempt}}', [
            'id' => $this->primaryKey()->unsigned(),
            'verification_id_hash' => $this->char(64)->notNull(),
            'status' => $this->string(32)->notNull(),
            'attempt_count' => $this->integer()->notNull()->defaultValue(1),
            'id_persona' => $this->integer()->null(),
            'id_user' => $this->integer()->null(),
            'last_attempt_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex(
            'uq_identity_signup_verification_hash',
            '{{%identity_signup_attempt}}',
            'verification_id_hash',
            true
        );
        $this->createIndex(
            'idx_identity_signup_status_updated',
            '{{%identity_signup_attempt}}',
            ['status', 'updated_at']
        );
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%identity_signup_attempt}}', true) === null) {
            return;
        }

        $this->dropTable('{{%identity_signup_attempt}}');
    }
}
