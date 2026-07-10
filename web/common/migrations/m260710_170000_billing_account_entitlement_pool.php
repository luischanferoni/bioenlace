<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Cuentas comerciales (Ministerio/Red/Efector) + entitlements en pool + backfill.
 */
class m260710_170000_billing_account_entitlement_pool extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $account = '{{%billing_account}}';
        if ($this->db->schema->getTableSchema($account, true) === null) {
            $this->createTable($account, [
                'id' => $this->primaryKey()->unsigned(),
                'nombre' => $this->string(255)->notNull(),
                'tipo' => $this->string(20)->notNull()->comment('MINISTERIO|RED|EFECTOR'),
                'notas' => $this->text()->null(),
                'activo' => $this->tinyInteger(1)->notNull()->defaultValue(1),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->dateTime()->null(),
                'deleted_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
                'deleted_by' => $this->integer()->null(),
            ]);
            $this->createIndex('idx_billing_account_tipo_activo', $account, ['tipo', 'activo']);
        }

        $member = '{{%billing_account_efector}}';
        if ($this->db->schema->getTableSchema($member, true) === null) {
            $this->createTable($member, [
                'id' => $this->primaryKey()->unsigned(),
                'id_billing_account' => $this->integer()->unsigned()->notNull(),
                'id_efector' => $this->integer()->notNull(),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->dateTime()->null(),
                'deleted_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
                'deleted_by' => $this->integer()->null(),
            ]);
            $this->createIndex('idx_billing_account_efector_account', $member, ['id_billing_account', 'deleted_at']);
            $this->createIndex('idx_billing_account_efector_efector', $member, ['id_efector', 'deleted_at']);
        }

        $ent = '{{%billing_account_encounter_entitlement}}';
        if ($this->db->schema->getTableSchema($ent, true) === null) {
            $this->createTable($ent, [
                'id' => $this->primaryKey()->unsigned(),
                'id_billing_account' => $this->integer()->unsigned()->notNull(),
                'encounter_class' => $this->string(10)->notNull(),
                'max_pes' => $this->integer()->unsigned()->null(),
                'pending_max_pes' => $this->integer()->unsigned()->null(),
                'pending_effective_on' => $this->date()->null(),
                'dictado_incluido' => $this->tinyInteger(1)->notNull()->defaultValue(0),
                'videollamada_permitida' => $this->tinyInteger(1)->notNull()->defaultValue(0),
                'activo' => $this->tinyInteger(1)->notNull()->defaultValue(1),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->dateTime()->null(),
                'deleted_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
                'deleted_by' => $this->integer()->null(),
            ]);
            $this->createIndex(
                'idx_billing_acct_entitlement_account_class',
                $ent,
                ['id_billing_account', 'encounter_class', 'deleted_at']
            );
        }

        $this->backfillFromLegacy();
    }

    private function backfillFromLegacy(): void
    {
        $legacy = $this->db->schema->getTableSchema('{{%efector_encounter_entitlement}}', true);
        if ($legacy === null) {
            return;
        }

        $rows = (new Query())
            ->from('{{%efector_encounter_entitlement}}')
            ->where(['deleted_at' => null, 'activo' => 1])
            ->orderBy(['id_efector' => SORT_ASC, 'encounter_class' => SORT_ASC])
            ->all($this->db);

        $byEfector = [];
        foreach ($rows as $row) {
            $idEfector = (int) $row['id_efector'];
            $byEfector[$idEfector][] = $row;
        }

        $efectores = [];
        if ($byEfector !== []) {
            $efectores = (new Query())
                ->select(['id_efector', 'nombre'])
                ->from('{{%efectores}}')
                ->where(['id_efector' => array_keys($byEfector)])
                ->indexBy('id_efector')
                ->all($this->db);
        }

        foreach ($byEfector as $idEfector => $ents) {
            $exists = (new Query())
                ->from('{{%billing_account_efector}}')
                ->where(['id_efector' => $idEfector, 'deleted_at' => null])
                ->exists($this->db);
            if ($exists) {
                continue;
            }

            $nombreEfector = (string) ($efectores[$idEfector]['nombre'] ?? ('Efector #' . $idEfector));
            $this->insert('{{%billing_account}}', [
                'nombre' => 'Licencia: ' . $nombreEfector,
                'tipo' => 'EFECTOR',
                'notas' => 'Backfill desde efector_encounter_entitlement',
                'activo' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $accountId = (int) $this->db->getLastInsertID();

            $this->insert('{{%billing_account_efector}}', [
                'id_billing_account' => $accountId,
                'id_efector' => $idEfector,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            foreach ($ents as $entRow) {
                $class = (string) $entRow['encounter_class'];
                $dictado = in_array($class, ['EMER', 'IMP'], true) ? 1 : 0;
                $video = $class === 'AMB' ? 0 : 0;
                $this->insert('{{%billing_account_encounter_entitlement}}', [
                    'id_billing_account' => $accountId,
                    'encounter_class' => $class,
                    'max_pes' => $entRow['max_pes'],
                    'pending_max_pes' => $entRow['pending_max_pes'] ?? null,
                    'pending_effective_on' => $entRow['pending_effective_on'] ?? null,
                    'dictado_incluido' => $dictado,
                    'videollamada_permitida' => $video,
                    'activo' => (int) ($entRow['activo'] ?? 1),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }
        foreach (['billing_account_encounter_entitlement', 'billing_account_efector', 'billing_account'] as $t) {
            $table = '{{%' . $t . '}}';
            if ($this->db->schema->getTableSchema($table, true) !== null) {
                $this->dropTable($table);
            }
        }
    }
}
