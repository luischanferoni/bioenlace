<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Renames the staff encounter summary RBAC routes and preserves their grants.
 */
class m261021_110000_rename_api_encounter_staff_summary_route extends Migration
{
    private const ROUTE_RENAMES = [
        '/api/clinical/encounter/ver-consulta-como-staff'
            => '/api/clinical/encounter/staff-summary',
        '/api/clinical/encounter-staff-summary/ver-consulta-como-staff'
            => '/api/clinical/encounter-staff-summary/staff-summary',
    ];

    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m261021_110000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        foreach (self::ROUTE_RENAMES as $oldRoute => $newRoute) {
            $this->renameRoute($oldRoute, $newRoute);
        }
    }

    public function safeDown(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        foreach (array_reverse(self::ROUTE_RENAMES, true) as $oldRoute => $newRoute) {
            $this->renameRoute($newRoute, $oldRoute);
        }
    }

    private function renameRoute(string $oldRoute, string $newRoute): void
    {
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $authItemSchema = $this->db->schema->getTableSchema($authItem, true);
        if ($authItemSchema === null) {
            echo "m261021_110000: sin auth_item, omitido.\n";

            return;
        }

        $item = (new Query())->from($authItem)->where(['name' => $oldRoute])->one($this->db);
        if ($item !== false && !(new Query())->from($authItem)->where(['name' => $newRoute])->exists($this->db)) {
            $item['name'] = $newRoute;
            $this->db->createCommand()->insert($authItem, $item)->execute();
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $relations = (new Query())
                ->from($childTable)
                ->where(['or', ['parent' => $oldRoute], ['child' => $oldRoute]])
                ->all($this->db);

            foreach ($relations as $relation) {
                $renamed = [
                    'parent' => $relation['parent'] === $oldRoute ? $newRoute : $relation['parent'],
                    'child' => $relation['child'] === $oldRoute ? $newRoute : $relation['child'],
                ];
                if (!(new Query())->from($childTable)->where($renamed)->exists($this->db)) {
                    $this->db->createCommand()->insert($childTable, $renamed)->execute();
                }
                $this->db->createCommand()->delete($childTable, [
                    'parent' => $relation['parent'],
                    'child' => $relation['child'],
                ])->execute();
            }
        }

        $assignmentTable = $this->db->schema->getRawTableName('{{%auth_assignment}}');
        if ($this->db->schema->getTableSchema($assignmentTable, true) !== null) {
            $assignments = (new Query())
                ->from($assignmentTable)
                ->where(['item_name' => $oldRoute])
                ->all($this->db);

            foreach ($assignments as $assignment) {
                $renamed = $assignment;
                $renamed['item_name'] = $newRoute;
                if (!(new Query())->from($assignmentTable)->where([
                    'item_name' => $newRoute,
                    'user_id' => $assignment['user_id'],
                ])->exists($this->db)) {
                    $this->db->createCommand()->insert($assignmentTable, $renamed)->execute();
                }
                $this->db->createCommand()->delete($assignmentTable, [
                    'item_name' => $oldRoute,
                    'user_id' => $assignment['user_id'],
                ])->execute();
            }
        }

        $this->db->createCommand()->delete($authItem, ['name' => $oldRoute])->execute();
    }
}
