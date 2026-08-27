<?php

use yii\db\Migration;

/**
 * Renombra dominio EMER/IMP cobertura/plantel → horario:
 * tablas, índices, rutas API RBAC, intents y shortcut group.
 *
 * Idempotente.
 */
class m260827_120000_rename_profesional_cobertura_to_horario extends Migration
{
    private const OLD_ROUTE_PREFIX = '/api/profesional-cobertura';
    private const NEW_ROUTE_PREFIX = '/api/profesional-horarios';

    private const OLD_INTENT_STAFF = 'profesional-cobertura.gestionar-staff';
    private const NEW_INTENT_STAFF = 'profesional-horarios.gestionar-staff';

    private const OLD_INTENT_PROPIO = 'profesional-cobertura.gestionar-propio';
    private const NEW_INTENT_PROPIO = 'profesional-horarios.gestionar-propio';

    private const OLD_GROUP = 'profesional-cobertura';
    private const NEW_GROUP = 'profesional-horarios';

    public function safeUp()
    {
        $this->renameTablesAndIndexes();
        $this->renameApiRoutes();
        $this->renameIntentStaff();
        $this->migrateIntentPropioOntoCanonical();
        $this->mergeShortcutGroup();
        $this->updateAuthDescriptions();
        $this->bumpRbacRevision();
    }

    public function safeDown()
    {
        echo "m260827_120000_rename_profesional_cobertura_to_horario: down no implementado (rename irreversible de vocabulario).\n";

        return false;
    }

    private function renameTablesAndIndexes(): void
    {
        $oldMain = '{{%profesional_cobertura}}';
        $newMain = '{{%profesional_horario}}';
        if ($this->db->schema->getTableSchema($oldMain, true) !== null
            && $this->db->schema->getTableSchema($newMain, true) === null) {
            $this->renameTable($oldMain, $newMain);
        }

        $oldPlantilla = '{{%profesional_cobertura_plantilla}}';
        $newPlantilla = '{{%profesional_horario_plantilla}}';
        if ($this->db->schema->getTableSchema($oldPlantilla, true) !== null
            && $this->db->schema->getTableSchema($newPlantilla, true) === null) {
            $this->renameTable($oldPlantilla, $newPlantilla);
        }

        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $this->renameIndexIfExists('profesional_horario', 'idx_profesional_cobertura_persona_efector', 'idx_profesional_horario_persona_efector');
        $this->renameIndexIfExists('profesional_horario', 'idx_profesional_cobertura_efector_class_inicio', 'idx_profesional_horario_efector_class_inicio');
        $this->renameIndexIfExists('profesional_horario', 'idx_profesional_cobertura_intervalo', 'idx_profesional_horario_intervalo');
        $this->renameIndexIfExists('profesional_horario', 'idx_profesional_cobertura_deleted', 'idx_profesional_horario_deleted');
        $this->renameIndexIfExists('profesional_horario_plantilla', 'idx_pc_plantilla_persona_efector_clase', 'idx_ph_plantilla_persona_efector_clase');
    }

    private function renameIndexIfExists(string $tableRaw, string $oldName, string $newName): void
    {
        $schema = $this->db->schema->getTableSchema('{{%' . $tableRaw . '}}', true);
        if ($schema === null) {
            return;
        }
        $dbName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        if (!is_string($dbName) || $dbName === '') {
            return;
        }
        $existsOld = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = :db AND table_name = :t AND index_name = :i',
            [':db' => $dbName, ':t' => $tableRaw, ':i' => $oldName]
        )->queryScalar();
        $existsNew = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = :db AND table_name = :t AND index_name = :i',
            [':db' => $dbName, ':t' => $tableRaw, ':i' => $newName]
        )->queryScalar();
        if ($existsOld > 0 && $existsNew === 0) {
            $this->execute('ALTER TABLE `' . $tableRaw . '` RENAME INDEX `' . $oldName . '` TO `' . $newName . '`');
        }
    }

    private function renameApiRoutes(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }
        $schema = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($schema, true) === null) {
            return;
        }
        $from = $this->db->quoteValue(self::OLD_ROUTE_PREFIX);
        $to = $this->db->quoteValue(self::NEW_ROUTE_PREFIX);

        $this->execute(
            "UPDATE {$schema} SET `name` = REPLACE(`name`, {$from}, {$to}) WHERE `name` LIKE "
            . $this->db->quoteValue(self::OLD_ROUTE_PREFIX . '%')
        );
        $this->execute(
            "UPDATE {$schema} SET `description` = REPLACE(`description`, 'cobertura', 'horario') WHERE `name` LIKE "
            . $this->db->quoteValue(self::NEW_ROUTE_PREFIX . '%')
            . " AND `description` LIKE '%cobertura%'"
        );

        $child = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($child, true) !== null) {
            $this->execute(
                "UPDATE {$child} SET `parent` = REPLACE(`parent`, {$from}, {$to}) WHERE `parent` LIKE "
                . $this->db->quoteValue(self::OLD_ROUTE_PREFIX . '%')
            );
            $this->execute(
                "UPDATE {$child} SET `child` = REPLACE(`child`, {$from}, {$to}) WHERE `child` LIKE "
                . $this->db->quoteValue(self::OLD_ROUTE_PREFIX . '%')
            );
        }
    }

    private function renameIntentStaff(): void
    {
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $old = self::OLD_INTENT_STAFF;
        $new = self::NEW_INTENT_STAFF;
        $oldExists = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM {$authItem} WHERE name = :n",
            [':n' => $old]
        )->queryScalar();
        if ($oldExists === 0) {
            return;
        }
        $newExists = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM {$authItem} WHERE name = :n",
            [':n' => $new]
        )->queryScalar();

        if ($newExists === 0) {
            $this->update('{{%auth_item}}', [
                'name' => $new,
                'description' => 'Horarios de guardia / internación de un profesional',
            ], ['name' => $old]);
            if ($this->db->schema->getTableSchema($childTable, true) !== null) {
                $this->execute(
                    "UPDATE {$childTable} SET parent = :new WHERE parent = :old",
                    [':new' => $new, ':old' => $old]
                );
                $this->execute(
                    "UPDATE {$childTable} SET child = :new WHERE child = :old",
                    [':new' => $new, ':old' => $old]
                );
            }
        } else {
            $this->copyChildEdgesThenDeleteItem($old, $new);
        }
    }

    /**
     * Grants de profesional-cobertura.gestionar-propio → profesional-horarios.gestionar-propio; borra el intent viejo.
     */
    private function migrateIntentPropioOntoCanonical(): void
    {
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }
        $old = self::OLD_INTENT_PROPIO;
        $new = self::NEW_INTENT_PROPIO;
        $oldExists = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM {$authItem} WHERE name = :n",
            [':n' => $old]
        )->queryScalar();
        if ($oldExists === 0) {
            return;
        }
        $newExists = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM {$authItem} WHERE name = :n",
            [':n' => $new]
        )->queryScalar();
        if ($newExists === 0) {
            $this->update('{{%auth_item}}', [
                'name' => $new,
                'description' => 'Configurar mis horarios (ambulatorio / guardia / internación)',
            ], ['name' => $old]);
            $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
            if ($this->db->schema->getTableSchema($childTable, true) !== null) {
                $this->execute(
                    "UPDATE {$childTable} SET parent = :new WHERE parent = :old",
                    [':new' => $new, ':old' => $old]
                );
                $this->execute(
                    "UPDATE {$childTable} SET child = :new WHERE child = :old",
                    [':new' => $new, ':old' => $old]
                );
            }

            return;
        }
        $this->copyChildEdgesThenDeleteItem($old, $new);
    }

    private function copyChildEdgesThenDeleteItem(string $oldName, string $newName): void
    {
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $asParent = $this->db->createCommand(
                "SELECT child FROM {$childTable} WHERE parent = :old",
                [':old' => $oldName]
            )->queryColumn();
            foreach ($asParent as $child) {
                $exists = (int) $this->db->createCommand(
                    "SELECT COUNT(*) FROM {$childTable} WHERE parent = :p AND child = :c",
                    [':p' => $newName, ':c' => $child]
                )->queryScalar();
                if ($exists === 0) {
                    $this->insert('{{%auth_item_child}}', ['parent' => $newName, 'child' => $child]);
                }
            }
            $asChild = $this->db->createCommand(
                "SELECT parent FROM {$childTable} WHERE child = :old",
                [':old' => $oldName]
            )->queryColumn();
            foreach ($asChild as $parent) {
                $exists = (int) $this->db->createCommand(
                    "SELECT COUNT(*) FROM {$childTable} WHERE parent = :p AND child = :c",
                    [':p' => $parent, ':c' => $newName]
                )->queryScalar();
                if ($exists === 0) {
                    $this->insert('{{%auth_item_child}}', ['parent' => $parent, 'child' => $newName]);
                }
            }
            $this->delete('{{%auth_item_child}}', ['or', ['parent' => $oldName], ['child' => $oldName]]);
        }
        $this->delete('{{%auth_item}}', ['name' => $oldName]);
    }

    private function mergeShortcutGroup(): void
    {
        $table = '{{%assistant_shortcut_group}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }
        $oldExists = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM {$this->db->schema->getRawTableName($table)} WHERE group_id = :g",
            [':g' => self::OLD_GROUP]
        )->queryScalar();
        if ($oldExists === 0) {
            return;
        }
        $newExists = (int) $this->db->createCommand(
            "SELECT COUNT(*) FROM {$this->db->schema->getRawTableName($table)} WHERE group_id = :g",
            [':g' => self::NEW_GROUP]
        )->queryScalar();
        if ($newExists === 0) {
            $this->update($table, [
                'group_id' => self::NEW_GROUP,
                'label' => 'Horarios',
            ], ['group_id' => self::OLD_GROUP]);
        } else {
            $this->delete($table, ['group_id' => self::OLD_GROUP]);
            $this->update($table, ['label' => 'Horarios'], ['group_id' => self::NEW_GROUP]);
        }
    }

    private function updateAuthDescriptions(): void
    {
        $authItem = '{{%auth_item}}';
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }
        $this->update($authItem, [
            'description' => 'Configurar mis horarios (ambulatorio / guardia / internación)',
        ], ['name' => self::NEW_INTENT_PROPIO]);
        $this->update($authItem, [
            'description' => 'Horarios de guardia / internación de un profesional',
        ], ['name' => self::NEW_INTENT_STAFF]);
    }

    private function bumpRbacRevision(): void
    {
        $table = '{{%auth_item}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }
        // Touch a known route so ApiGhost caches refresh on next request if keyed by updated_at.
        $this->update($table, ['updated_at' => time()], [
            'name' => self::NEW_ROUTE_PREFIX . '/gestionar',
        ]);
    }
}
