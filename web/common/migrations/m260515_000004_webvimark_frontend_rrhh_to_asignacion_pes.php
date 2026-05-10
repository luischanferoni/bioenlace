<?php

use yii\db\Migration;

/**
 * Webvimark RBAC: rutas web `rrhh` → `asignacion-pes` (CRUD PES y endpoints auxiliares).
 *
 * Idempotente vía REPLACE con prefijos largos primero.
 */
class m260515_000004_webvimark_frontend_rrhh_to_asignacion_pes extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260515_000004: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260515_000004: sin tabla auth_item, omitido.\n";

            return;
        }

        $pairs = [
            ['/frontend/rrhh/', '/frontend/asignacion-pes/'],
            ['/rrhh/', '/asignacion-pes/'],
        ];

        foreach ($pairs as [$from, $to]) {
            $this->replaceRoutePrefix($authItem, '`name`', $from, $to);
        }

        $child = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($child, true) !== null) {
            foreach ($pairs as [$from, $to]) {
                $this->replaceRoutePrefix($child, '`parent`', $from, $to);
                $this->replaceRoutePrefix($child, '`child`', $from, $to);
            }
        }

        $assignment = $this->db->schema->getRawTableName('{{%auth_assignment}}');
        if ($this->db->schema->getTableSchema($assignment, true) !== null
            && $this->columnExists($assignment, 'item_name')) {
            foreach ($pairs as [$from, $to]) {
                $this->replaceRoutePrefix($assignment, '`item_name`', $from, $to);
            }
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $pairs = [
            ['/frontend/asignacion-pes/', '/frontend/rrhh/'],
            ['/asignacion-pes/', '/rrhh/'],
        ];

        foreach ($pairs as [$from, $to]) {
            $this->replaceRoutePrefix($authItem, '`name`', $from, $to);
        }

        $child = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($child, true) !== null) {
            foreach ($pairs as [$from, $to]) {
                $this->replaceRoutePrefix($child, '`parent`', $from, $to);
                $this->replaceRoutePrefix($child, '`child`', $from, $to);
            }
        }

        $assignment = $this->db->schema->getRawTableName('{{%auth_assignment}}');
        if ($this->db->schema->getTableSchema($assignment, true) !== null
            && $this->columnExists($assignment, 'item_name')) {
            foreach ($pairs as [$from, $to]) {
                $this->replaceRoutePrefix($assignment, '`item_name`', $from, $to);
            }
        }
    }

    private function replaceRoutePrefix(string $table, string $column, string $from, string $to): void
    {
        $fromQ = $this->db->quoteValue($from);
        $toQ = $this->db->quoteValue($to);
        $like = $this->db->quoteValue($from . '%');
        $this->execute(
            "UPDATE {$table} SET {$column} = REPLACE({$column}, {$fromQ}, {$toQ}) WHERE {$column} LIKE {$like}"
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
