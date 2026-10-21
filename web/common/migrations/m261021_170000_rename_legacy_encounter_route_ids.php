<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Renames the retired consultation route IDs and preserves their RBAC grants.
 */
class m261021_170000_rename_legacy_encounter_route_ids extends Migration
{
    private const ROUTE_RENAMES = [
        '/api/consulta-async/solicitar-como-paciente'
            => '/api/encounter-async/solicitar-como-paciente',
        '/api/consulta-async/tomar-como-staff'
            => '/api/encounter-async/tomar-como-staff',
        '/api/consulta-async/cerrar-como-staff'
            => '/api/encounter-async/cerrar-como-staff',
        '/api/consulta-async/cancelar-como-paciente'
            => '/api/encounter-async/cancelar-como-paciente',
        '/api/consultas-seguimiento/paso'
            => '/api/encounter-followup/paso',
        '/api/consultas-seguimiento/hub'
            => '/api/encounter-followup/hub',
        '/api/consultas-seguimiento/condicion-acciones'
            => '/api/encounter-followup/condicion-acciones',
        '/api/consulta-chat/listar-mensajes'
            => '/api/encounter-chat/listar-mensajes',
        '/api/consulta-chat/enviar'
            => '/api/encounter-chat/enviar',
        '/api/consulta-chat/subir'
            => '/api/encounter-chat/subir',
        '/api/consulta-chat/estado'
            => '/api/encounter-chat/estado',
        '/api/motivos-consulta/listar-mensajes'
            => '/api/encounter-reasons/listar-mensajes',
        '/api/motivos-consulta/enviar'
            => '/api/encounter-reasons/enviar',
        '/api/motivos-consulta/subir'
            => '/api/encounter-reasons/subir',
    ];

    public function safeUp(): void
    {
        foreach (self::ROUTE_RENAMES as $oldRoute => $newRoute) {
            $this->renameRoute($oldRoute, $newRoute);
        }
    }

    public function safeDown(): void
    {
        foreach (array_reverse(self::ROUTE_RENAMES, true) as $oldRoute => $newRoute) {
            $this->renameRoute($newRoute, $oldRoute);
        }
    }

    private function renameRoute(string $oldRoute, string $newRoute): void
    {
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $authItemSchema = $this->db->schema->getTableSchema($authItem, true);
        if ($authItemSchema === null) {
            echo "m261021_170000: sin auth_item, omitido.\n";

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
