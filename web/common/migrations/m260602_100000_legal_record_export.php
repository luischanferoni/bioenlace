<?php

use yii\db\Migration;

/**
 * Cola de expediente legal (export PDF staff, async).
 */
class m260602_100000_legal_record_export extends Migration
{
    public function safeUp()
    {
        $request = '{{%legal_record_export_request}}';
        if ($this->db->schema->getTableSchema($request, true) === null) {
            $this->createTable($request, [
                'id' => $this->primaryKey(),
                'subject_persona_id' => $this->integer()->notNull(),
                'id_efector' => $this->integer()->null(),
                'requested_by_user_id' => $this->integer()->notNull(),
                'requested_by_persona_id' => $this->integer()->null(),
                'estado' => $this->string(20)->notNull()->defaultValue('PENDIENTE'),
                'file_path' => $this->string(512)->null(),
                'file_size' => $this->integer()->null(),
                'ultimo_error' => $this->text()->null(),
                'intentos' => $this->integer()->notNull()->defaultValue(0),
                'ready_at' => $this->dateTime()->null(),
                'downloaded_at' => $this->dateTime()->null(),
                'downloaded_by_user_id' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ix_lre_request_subject', $request, ['subject_persona_id', 'created_at']);
            $this->createIndex('ix_lre_request_estado', $request, ['estado', 'created_at']);
            $this->createIndex('ix_lre_request_solicitante', $request, ['requested_by_user_id', 'created_at']);
        }

        $audit = '{{%legal_record_export_audit}}';
        if ($this->db->schema->getTableSchema($audit, true) === null) {
            $this->createTable($audit, [
                'id' => $this->primaryKey(),
                'request_id' => $this->integer()->notNull(),
                'event_type' => $this->string(32)->notNull(),
                'id_user' => $this->integer()->null(),
                'meta_json' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ix_lre_audit_request', $audit, ['request_id', 'created_at']);
            $this->addForeignKey(
                'fk_lre_audit_request',
                $audit,
                'request_id',
                $request,
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        $audit = '{{%legal_record_export_audit}}';
        if ($this->db->schema->getTableSchema($audit, true) !== null) {
            $this->dropTable($audit);
        }
        $request = '{{%legal_record_export_request}}';
        if ($this->db->schema->getTableSchema($request, true) !== null) {
            $this->dropTable($request);
        }
    }
}
