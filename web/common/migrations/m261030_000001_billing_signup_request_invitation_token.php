<?php

use yii\db\Migration;

/**
 * Alta de ministerio asistida: token de invitación de un solo uso para completar
 * el KYC desde la app (fase 6 del plan onboarding-mobile-didit).
 */
class m261030_000001_billing_signup_request_invitation_token extends Migration
{
    public function safeUp()
    {
        $req = '{{%billing_signup_request}}';
        $schema = $this->db->schema->getTableSchema($req, true);
        if ($schema === null) {
            return;
        }
        if (!isset($schema->columns['invitation_token_hash'])) {
            $this->addColumn($req, 'invitation_token_hash', $this->string(64)->null()->after('notas'));
            $this->createIndex('idx_billing_signup_invitation_token', $req, ['invitation_token_hash', 'deleted_at']);
        }
        if (!isset($schema->columns['invitation_expires_at'])) {
            $this->addColumn($req, 'invitation_expires_at', $this->dateTime()->null()->after('invitation_token_hash'));
        }
    }

    public function safeDown()
    {
        $req = '{{%billing_signup_request}}';
        $schema = $this->db->schema->getTableSchema($req, true);
        if ($schema === null) {
            return;
        }
        if (isset($schema->columns['invitation_token_hash'])) {
            $this->dropIndex('idx_billing_signup_invitation_token', $req);
            $this->dropColumn($req, 'invitation_token_hash');
        }
        if (isset($schema->columns['invitation_expires_at'])) {
            $this->dropColumn($req, 'invitation_expires_at');
        }
    }
}
