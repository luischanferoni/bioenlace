<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Limpieza RBAC post-webvimark: rutas MVC frontend retiradas, prefijo /api/v1 duplicado y huérfanos.
 */
class m260613_120000_rbac_cleanup_post_webvimark extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260613_120000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $itemTable = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($itemTable, true) === null) {
            echo "m260613_120000: sin auth_item, omitido.\n";

            return;
        }

        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;

        // 1) Rutas MVC /frontend/* (SPA usa API; backend admin usa /personas/* sin prefijo)
        $frontendRoutes = (new Query())
            ->select(['name'])
            ->from($itemTable)
            ->where(['type' => 3])
            ->andWhere(['like', 'name', '/frontend/%', false])
            ->column($this->db);

        if ($frontendRoutes !== [] && $hasChild) {
            $this->db->createCommand()->delete($childTable, [
                'or',
                ['parent' => $frontendRoutes],
                ['child' => $frontendRoutes],
            ])->execute();
        }
        if ($frontendRoutes !== []) {
            $removed = (int) $this->db->createCommand()->delete($itemTable, [
                'and',
                ['type' => 3],
                ['name' => $frontendRoutes],
            ])->execute();
            echo "m260613_120000: rutas /frontend/* eliminadas: {$removed}\n";
        }

        // 2) Rutas /api/v1/* cuando existe equivalente /api/* (canónico en Bioenlace)
        $v1Routes = (new Query())
            ->select(['name'])
            ->from($itemTable)
            ->where(['type' => 3])
            ->andWhere(['like', 'name', '/api/v1/%', false])
            ->column($this->db);

        $v1Dropped = [];
        foreach ($v1Routes as $route) {
            if (!is_string($route)) {
                continue;
            }
            $canonical = preg_replace('#^/api/v\d+/#', '/api/', $route, 1);
            if (!is_string($canonical) || $canonical === $route) {
                continue;
            }
            if ((new Query())->from($itemTable)->where(['name' => $canonical, 'type' => 3])->exists($this->db)) {
                $v1Dropped[] = $route;
            }
        }

        if ($v1Dropped !== [] && $hasChild) {
            $this->db->createCommand()->delete($childTable, [
                'or',
                ['parent' => $v1Dropped],
                ['child' => $v1Dropped],
            ])->execute();
        }
        if ($v1Dropped !== []) {
            $removed = (int) $this->db->createCommand()->delete($itemTable, [
                'and',
                ['type' => 3],
                ['name' => $v1Dropped],
            ])->execute();
            echo "m260613_120000: rutas /api/v1/* duplicadas eliminadas: {$removed}\n";
        }

        // 3) auth_item_child huérfanos
        if ($hasChild) {
            $orphanParents = (int) $this->db->createCommand(
                "DELETE c FROM {$childTable} c
                 LEFT JOIN {$itemTable} p ON p.name = c.parent
                 WHERE p.name IS NULL"
            )->execute();
            $orphanChildren = (int) $this->db->createCommand(
                "DELETE c FROM {$childTable} c
                 LEFT JOIN {$itemTable} ch ON ch.name = c.child
                 WHERE ch.name IS NULL"
            )->execute();
            echo "m260613_120000: auth_item_child huérfanos (parent/child): {$orphanParents}/{$orphanChildren}\n";
        }

        // 4) auth_item_group (solo UI webvimark)
        $groupTable = $this->db->schema->getRawTableName('{{%auth_item_group}}');
        if ($this->db->schema->getTableSchema($groupTable, true) !== null) {
            $this->update($itemTable, ['group_code' => null], ['not', ['group_code' => null]]);
            $groups = (int) $this->db->createCommand()->delete($groupTable)->execute();
            echo "m260613_120000: filas auth_item_group eliminadas: {$groups}\n";
        }
    }

    public function safeDown()
    {
        echo "m260613_120000: safeDown no restaura datos RBAC eliminados.\n";
    }
}
