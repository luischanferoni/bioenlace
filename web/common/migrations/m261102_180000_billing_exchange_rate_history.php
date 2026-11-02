<?php

use yii\db\Migration;

/**
 * Stores immutable, effective-dated exchange-rate history for commercial billing.
 */
class m261102_180000_billing_exchange_rate_history extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $table = '{{%billing_exchange_rate}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            $this->createTable($table, [
                'id' => $this->primaryKey()->unsigned(),
                'currency_base' => $this->char(3)->notNull(),
                'currency_quote' => $this->char(3)->notNull(),
                'rate' => $this->decimal(12, 4)->notNull(),
                'effective_date' => $this->date()->notNull(),
                'source' => $this->string(64)->notNull(),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            ]);
        }
        if (!$this->indexExists($table, 'uq_billing_exchange_rate_effective_date')) {
            $this->createIndex(
                'uq_billing_exchange_rate_effective_date',
                $table,
                ['currency_base', 'currency_quote', 'effective_date'],
                true
            );
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        if ($this->db->schema->getTableSchema('{{%billing_exchange_rate}}', true) !== null) {
            $this->dropTable('{{%billing_exchange_rate}}');
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        $rawTable = $this->db->schema->getRawTableName($table);
        $indexes = $this->db->schema->getTableIndexes($rawTable, true);

        return isset($indexes[$name]);
    }
}
