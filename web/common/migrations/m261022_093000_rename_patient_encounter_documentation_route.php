<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Renames the patient MVC encounter documentation route and preserves its RBAC grants.
 */
class m261022_093000_rename_patient_encounter_documentation_route extends Migration
{
    private const OLD_ROUTE = '/paciente/ver-consulta';
    private const NEW_ROUTE = '/paciente/encounter-documentation';

    public function safeUp(): void
    {
        $this->renameRoute(self::OLD_ROUTE, self::NEW_ROUTE);
    }

    public function safeDown(): void
    {
        $this->renameRoute(self::NEW_ROUTE, self::OLD_ROUTE);
    }

    private function renameRoute(string $oldRoute, string $newRoute): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m261022_093000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m261022_093000: sin auth_item, omitido.\n";

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
