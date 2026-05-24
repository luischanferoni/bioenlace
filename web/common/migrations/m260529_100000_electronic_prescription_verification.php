<?php

use yii\db\Migration;

/**
 * Receta electrónica — verificación e integridad (Fase 2).
 */
class m260529_100000_electronic_prescription_verification extends Migration
{
    public function safeUp()
    {
        $table = '{{%electronic_prescription}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        $schema = $this->db->schema->getTableSchema($table, true);

        if (!isset($schema->columns['verification_token'])) {
            $this->addColumn($table, 'verification_token', $this->string(64)->null()->unique());
        }
        if (!isset($schema->columns['document_hash'])) {
            $this->addColumn($table, 'document_hash', $this->string(64)->null());
        }
        if (!isset($schema->columns['signature_provider'])) {
            $this->addColumn(
                $table,
                'signature_provider',
                $this->string(64)->null()->comment('bioenlace-internal | proveedor PKI futuro')
            );
        }
        if (!isset($schema->columns['signed_at'])) {
            $this->addColumn($table, 'signed_at', $this->dateTime()->null());
        }
    }

    public function safeDown()
    {
        $table = '{{%electronic_prescription}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        foreach (['signed_at', 'signature_provider', 'document_hash', 'verification_token'] as $col) {
            if ($this->db->schema->getTableSchema($table, true)->getColumn($col) !== null) {
                $this->dropColumn($table, $col);
            }
        }
    }
}
