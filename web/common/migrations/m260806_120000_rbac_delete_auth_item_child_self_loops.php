<?php

use yii\db\Migration;

/**
 * Self-loops en auth_item_child (parent = child) hacen OOM en DbManager::getChildrenRecursive.
 * Origen típico: migrateRoleGrants desde una ruta que el propio intent ya tiene como hijo.
 */
class m260806_120000_rbac_delete_auth_item_child_self_loops extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $this->db->createCommand()->delete($childTable, '[[parent]] = [[child]]')->execute();

        try {
            if (\Yii::$app->has('authManager') && method_exists(\Yii::$app->authManager, 'invalidateCache')) {
                \Yii::$app->authManager->invalidateCache();
            }
        } catch (\Throwable $e) {
            // Migración no debe fallar por caché RBAC.
        }

        try {
            if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
                \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
            }
        } catch (\Throwable $e) {
            // Migración no debe fallar por revisión.
        }
    }

    public function safeDown()
    {
        // Irreversible: los self-loops no deben restaurarse.
    }
}
