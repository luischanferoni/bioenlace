<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: chat clínico consulta-chat (listar / enviar / subir / estado).
 *
 * Paciente hereda de quienes pueden solicitar async; staff de quienes pueden tomar.
 * El acceso de dominio al encounter sigue en ConsultaChatController.
 */
class m260720_120000_api_consulta_chat_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var list<string> */
    private const ROUTES = [
        '/api/consulta-chat/listar-mensajes',
        '/api/consulta-chat/enviar',
        '/api/consulta-chat/subir',
        '/api/consulta-chat/estado',
    ];

    private const PACIENTE_PARENT = '/api/consulta-async/solicitar-como-paciente';

    private const STAFF_PARENT = '/api/consulta-async/tomar-como-staff';

    public function safeUp()
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
        foreach (self::ROUTES as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API consulta-chat: ' . $route,
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
        }

        $this->linkChildren($childTable, self::PACIENTE_PARENT, self::ROUTES);
        $this->linkChildren($childTable, self::STAFF_PARENT, self::ROUTES);
    }

    /**
     * @param list<string> $routes
     */
    private function linkChildren(string $childTable, string $parentRoute, array $routes): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            foreach ($routes as $route) {
                if ((new Query())->from($childTable)->where([
                    'parent' => $parent,
                    'child' => $route,
                ])->exists($this->db)) {
                    continue;
                }
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $parent,
                    'child' => $route,
                ])->execute();
            }
        }
    }

    public function safeDown()
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

        foreach (self::ROUTES as $route) {
            $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
            $this->db->createCommand()->delete($authItem, ['name' => $route])->execute();
        }
    }
}
