<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Retira el intent internacion.alta-estructurada-flow: el alta es clínica en el encounter (como guardia).
 */
class m260814_150000_drop_internacion_alta_estructurada_intent_rbac extends Migration
{
    private const INTENT_ID = 'internacion.alta-estructurada-flow';

    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, [
                'or',
                ['child' => self::INTENT_ID],
                ['parent' => self::INTENT_ID],
            ])->execute();
        }

        $this->db->createCommand()->delete($authItem, ['name' => self::INTENT_ID])->execute();

        if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
            \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
        }
    }

    public function safeDown(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $now = time();
        if (!(new Query())->from($authItem)->where(['name' => self::INTENT_ID])->exists($this->db)) {
            $this->db->createCommand()->insert($authItem, [
                'name' => self::INTENT_ID,
                'type' => 2,
                'description' => 'Intent internacion.alta-estructurada-flow',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }

        foreach (['Medico', 'AdminEfector'] as $role) {
            if (!(new Query())->from($authItem)->where(['name' => $role])->exists($this->db)) {
                continue;
            }
            $exists = (new Query())
                ->from($childTable)
                ->where(['parent' => $role, 'child' => self::INTENT_ID])
                ->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $role,
                'child' => self::INTENT_ID,
            ])->execute();
        }

        if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
            \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
        }
    }
}
