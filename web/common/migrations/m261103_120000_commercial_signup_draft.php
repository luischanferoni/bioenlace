<?php

use yii\db\Migration;

class m261103_120000_commercial_signup_draft extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%commercial_signup_draft}}', true) !== null) {
            return;
        }

        $this->createTable('{{%commercial_signup_draft}}', [
            'id' => $this->primaryKey()->unsigned(),
            'id_persona' => $this->integer()->notNull(),
            'status' => $this->string(24)->notNull()->defaultValue('DRAFT'),
            'sector' => $this->string(12)->null(),
            'efector_json' => $this->text()->null(),
            'id_billing_account_ministerio' => $this->integer()->null(),
            'plan_json' => $this->text()->null(),
            'payer_email' => $this->string(255)->null(),
            'contact_phone' => $this->string(40)->null(),
            'current_step' => $this->smallInteger()->notNull()->defaultValue(0),
            'checkout_request_key' => $this->char(48)->null(),
            'checkout_key' => $this->string(64)->null(),
            'schema_version' => $this->smallInteger()->notNull()->defaultValue(1),
            'last_activity_at' => $this->dateTime()->notNull(),
            'expires_at' => $this->dateTime()->notNull(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex(
            'uq_commercial_signup_draft_persona',
            '{{%commercial_signup_draft}}',
            'id_persona',
            true
        );
        $this->createIndex(
            'idx_commercial_signup_draft_status_expiry',
            '{{%commercial_signup_draft}}',
            ['status', 'expires_at']
        );
        $this->createIndex(
            'idx_commercial_signup_draft_checkout',
            '{{%commercial_signup_draft}}',
            'checkout_key'
        );
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%commercial_signup_draft}}', true) !== null) {
            $this->dropTable('{{%commercial_signup_draft}}');
        }
    }
}
