<?php

use yii\db\Migration;

/**
 * Estado persistente para passkeys, desafíos, sesiones revocables y recuperación asistida.
 */
class m261030_000003_authentication_security_tables extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if (in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%auth_session}}', [
            'jti' => $this->string(64)->notNull(),
            'id_user' => $this->integer()->notNull(),
            'expires_at' => $this->integer()->notNull(),
            'revoked_at' => $this->integer()->null(),
            'client' => $this->string(64)->notNull()->defaultValue('unknown'),
            'created_at' => $this->integer()->notNull(),
            'PRIMARY KEY ([[jti]])',
        ], $tableOptions);
        $this->createIndex('idx_auth_session_user_active', '{{%auth_session}}', ['id_user', 'revoked_at']);
        $this->createIndex('idx_auth_session_expiry', '{{%auth_session}}', 'expires_at');
        $this->addForeignKey(
            'fk_auth_session_user',
            '{{%auth_session}}',
            'id_user',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%webauthn_credential}}', [
            'credential_id_hash' => $this->char(64)->notNull(),
            'id_user' => $this->integer()->notNull(),
            'credential_id' => $this->string(1024)->notNull(),
            'credential_source' => $this->text()->notNull(),
            'name' => $this->string(100)->notNull()->defaultValue('Passkey'),
            'created_at' => $this->integer()->notNull(),
            'last_used_at' => $this->integer()->null(),
            'revoked_at' => $this->integer()->null(),
            'PRIMARY KEY ([[credential_id_hash]])',
        ], $tableOptions);
        $this->createIndex('idx_webauthn_credential_user', '{{%webauthn_credential}}', ['id_user', 'revoked_at']);
        $this->addForeignKey(
            'fk_webauthn_credential_user',
            '{{%webauthn_credential}}',
            'id_user',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%auth_challenge}}', [
            'id' => $this->string(64)->notNull(),
            'id_user' => $this->integer()->null(),
            'purpose' => $this->string(32)->notNull(),
            'challenge_hash' => $this->char(64)->notNull(),
            'challenge' => $this->string(128)->notNull(),
            'session_hash' => $this->char(64)->null(),
            'rp_id' => $this->string(255)->notNull(),
            'origin' => $this->string(512)->notNull(),
            'state' => $this->string(24)->notNull()->defaultValue('pending'),
            'approved_user_id' => $this->integer()->null(),
            'expires_at' => $this->integer()->notNull(),
            'consumed_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'PRIMARY KEY ([[id]])',
        ], $tableOptions);
        $this->createIndex('idx_auth_challenge_expiry', '{{%auth_challenge}}', 'expires_at');
        $this->createIndex('idx_auth_challenge_user', '{{%auth_challenge}}', 'id_user');

        $this->createTable('{{%account_recovery_request}}', [
            'id' => $this->string(64)->notNull(),
            'id_user' => $this->integer()->notNull(),
            'status' => $this->string(24)->notNull()->defaultValue('pending'),
            'request_ip_hash' => $this->char(64)->notNull(),
            'request_note' => $this->string(500)->null(),
            'reviewed_by' => $this->integer()->null(),
            'reviewed_at' => $this->integer()->null(),
            'recovery_code_hash' => $this->char(64)->null(),
            'recovery_code_expires_at' => $this->integer()->null(),
            'recovery_code_used_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'PRIMARY KEY ([[id]])',
        ], $tableOptions);
        $this->createIndex('idx_account_recovery_status_created', '{{%account_recovery_request}}', ['status', 'created_at']);
        $this->createIndex('idx_account_recovery_user_status', '{{%account_recovery_request}}', ['id_user', 'status']);
        $this->addForeignKey(
            'fk_account_recovery_user',
            '{{%account_recovery_request}}',
            'id_user',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_account_recovery_reviewer',
            '{{%account_recovery_request}}',
            'reviewed_by',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_account_recovery_reviewer', '{{%account_recovery_request}}');
        $this->dropForeignKey('fk_account_recovery_user', '{{%account_recovery_request}}');
        $this->dropTable('{{%account_recovery_request}}');
        $this->dropTable('{{%auth_challenge}}');
        $this->dropForeignKey('fk_webauthn_credential_user', '{{%webauthn_credential}}');
        $this->dropTable('{{%webauthn_credential}}');
        $this->dropForeignKey('fk_auth_session_user', '{{%auth_session}}');
        $this->dropTable('{{%auth_session}}');
    }
}
