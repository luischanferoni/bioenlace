<?php

use yii\db\Migration;

/**
 * Persisted Mercado Pago subscription checkouts and idempotent webhook events.
 */
class m260829_110000_billing_mercadopago_subscription_checkout extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $payment = '{{%billing_payment}}';
        $paymentSchema = $this->db->schema->getTableSchema($payment, true);
        if ($paymentSchema !== null) {
            if (!isset($paymentSchema->columns['amount_ars'])) {
                $this->addColumn($payment, 'amount_ars', $this->decimal(12, 2)->null()->after('amount_usd'));
            }
            if (!isset($paymentSchema->columns['provider_subscription_id'])) {
                $this->addColumn($payment, 'provider_subscription_id', $this->string(100)->null()->after('external_reference'));
            }
            if (!isset($paymentSchema->columns['provider_payment_id'])) {
                $this->addColumn($payment, 'provider_payment_id', $this->string(100)->null()->after('provider_subscription_id'));
            }
            if (!$this->indexExists('uq_billing_payment_provider_payment', $payment)) {
                $this->createIndex('uq_billing_payment_provider_payment', $payment, 'provider_payment_id', true);
            }
        }

        $attempt = '{{%billing_checkout_attempt}}';
        if ($this->db->schema->getTableSchema($attempt, true) === null) {
            $this->createTable($attempt, [
                'id' => $this->primaryKey()->unsigned(),
                'checkout_key' => $this->string(64)->notNull(),
                'id_user' => $this->integer()->unsigned()->notNull(),
                'id_persona' => $this->integer()->unsigned()->notNull(),
                'perfil' => $this->string(24)->notNull(),
                'sector' => $this->string(12)->notNull(),
                'status' => $this->string(24)->notNull(),
                'id_billing_account' => $this->integer()->unsigned()->null(),
                'id_efector' => $this->integer()->unsigned()->null(),
                'reserved_sisa_code' => $this->string(15)->null(),
                'id_billing_account_ministerio' => $this->integer()->unsigned()->null(),
                'provider_subscription_id' => $this->string(100)->null(),
                'provider_payment_id' => $this->string(100)->null(),
                'checkout_url' => $this->string(2048)->null(),
                'payer_email' => $this->string(255)->notNull(),
                'contact_phone' => $this->string(40)->null(),
                'plan_json' => $this->db->getSchema()->createColumnSchemaBuilder('MEDIUMTEXT')->notNull(),
                'efector_json' => $this->db->getSchema()->createColumnSchemaBuilder('MEDIUMTEXT')->notNull(),
                'amount_usd' => $this->decimal(12, 2)->notNull(),
                'fx_rate' => $this->decimal(12, 4)->notNull(),
                'fx_rate_effective_date' => $this->date()->notNull(),
                'amount_net_ars' => $this->decimal(12, 2)->notNull(),
                'vat_rate' => $this->decimal(5, 4)->notNull(),
                'vat_ars' => $this->decimal(12, 2)->notNull(),
                'amount_total_ars' => $this->decimal(12, 2)->notNull(),
                'paid_through_at' => $this->dateTime()->null(),
                'provider_status' => $this->string(24)->null(),
                'provider_subscription_status' => $this->string(24)->null(),
                'last_provider_payload' => $this->db->getSchema()->createColumnSchemaBuilder('MEDIUMTEXT')->null(),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->dateTime()->null(),
                'deleted_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
                'deleted_by' => $this->integer()->null(),
            ]);
            $this->createIndex('uq_billing_checkout_key', $attempt, 'checkout_key', true);
            $this->createIndex('uq_billing_checkout_subscription', $attempt, 'provider_subscription_id', true);
            $this->createIndex('uq_billing_checkout_payment', $attempt, 'provider_payment_id', true);
            $this->createIndex('idx_billing_checkout_user_status', $attempt, ['id_user', 'status', 'deleted_at']);
            $this->createIndex('idx_billing_checkout_paid_through', $attempt, ['status', 'paid_through_at']);
            $this->createIndex('uq_billing_checkout_reserved_sisa_code', $attempt, 'reserved_sisa_code', true);
        }
        $attemptSchema = $this->db->schema->getTableSchema($attempt, true);
        if ($attemptSchema !== null && !isset($attemptSchema->columns['paid_through_at'])) {
            $this->addColumn($attempt, 'paid_through_at', $this->dateTime()->null()->after('amount_total_ars'));
        }
        if ($attemptSchema !== null && !isset($attemptSchema->columns['reserved_sisa_code'])) {
            $this->addColumn($attempt, 'reserved_sisa_code', $this->string(15)->null()->after('id_efector'));
        }
        if ($attemptSchema !== null && !$this->indexExists('idx_billing_checkout_paid_through', $attempt)) {
            $this->createIndex('idx_billing_checkout_paid_through', $attempt, ['status', 'paid_through_at']);
        }
        if ($attemptSchema !== null && !$this->indexExists('uq_billing_checkout_reserved_sisa_code', $attempt)) {
            $this->createIndex('uq_billing_checkout_reserved_sisa_code', $attempt, 'reserved_sisa_code', true);
        }

        $event = '{{%billing_webhook_event}}';
        if ($this->db->schema->getTableSchema($event, true) === null) {
            $this->createTable($event, [
                'id' => $this->bigPrimaryKey()->unsigned(),
                'event_key' => $this->string(190)->notNull(),
                'provider_event_id' => $this->string(100)->null(),
                'topic' => $this->string(64)->notNull(),
                'resource_id' => $this->string(100)->notNull(),
                'status' => $this->string(16)->notNull(),
                'payload_json' => $this->db->getSchema()->createColumnSchemaBuilder('MEDIUMTEXT')->notNull(),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'processed_at' => $this->dateTime()->null(),
            ]);
            $this->createIndex('uq_billing_webhook_event_key', $event, 'event_key', true);
            $this->createIndex('idx_billing_webhook_resource', $event, ['topic', 'resource_id']);
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        if ($this->db->schema->getTableSchema('{{%billing_webhook_event}}', true) !== null) {
            $this->dropTable('{{%billing_webhook_event}}');
        }
        if ($this->db->schema->getTableSchema('{{%billing_checkout_attempt}}', true) !== null) {
            $this->dropTable('{{%billing_checkout_attempt}}');
        }

        $payment = '{{%billing_payment}}';
        $schema = $this->db->schema->getTableSchema($payment, true);
        if ($schema !== null) {
            if (isset($schema->columns['provider_payment_id'])) {
                if ($this->indexExists('uq_billing_payment_provider_payment', $payment)) {
                    $this->dropIndex('uq_billing_payment_provider_payment', $payment);
                }
                $this->dropColumn($payment, 'provider_payment_id');
            }
            foreach (['provider_subscription_id', 'amount_ars'] as $column) {
                if (isset($schema->columns[$column])) {
                    $this->dropColumn($payment, $column);
                }
            }
        }
    }

    private function indexExists(string $name, string $table): bool
    {
        $rawTable = $this->db->schema->getRawTableName($table);
        $indexes = $this->db->schema->getTableIndexes($rawTable, true);

        foreach ($indexes as $index) {
            if ($index->name === $name) {
                return true;
            }
        }

        return false;
    }
}
