<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Asegura RBAC de consulta-chat para rol paciente (y herencias staff/paciente).
 *
 * Complementa m260720_120000: otorga también al rol `paciente` de forma directa
 * por si `solicitar-como-paciente` no tenía padres en auth_item_child.
 */
class m260720_130000_api_consulta_chat_paciente_grants extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROLE_PACIENTE = 'paciente';

    /** @var list<string> */
    private const ROUTES = [
        '/api/consulta-chat/listar-mensajes',
        '/api/consulta-chat/enviar',
        '/api/consulta-chat/subir',
        '/api/consulta-chat/estado',
    ];

    /** @var list<string> */
    private const PARENT_ROUTES = [
        '/api/consulta-async/solicitar-como-paciente',
        '/api/turnos/crear-como-paciente',
        '/api/consulta-async/tomar-como-staff',
        '/api/home/panel',
    ];

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
            $this->ensureChild($childTable, self::ROLE_PACIENTE, $route);
        }

        foreach (self::PARENT_ROUTES as $parentRoute) {
            $this->linkChildren($childTable, $parentRoute, self::ROUTES);
        }

        $this->bumpRbacRevision($authItem);
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
                $this->ensureChild($childTable, $parent, $route);
            }
        }
    }

    private function ensureChild(string $childTable, string $parent, string $child): void
    {
        if ((new Query())->from($childTable)->where([
            'parent' => $parent,
            'child' => $child,
        ])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($childTable, [
            'parent' => $parent,
            'child' => $child,
        ])->execute();
    }

    private function bumpRbacRevision(string $authItem): void
    {
        $now = time();
        if ((new Query())->from($authItem)->where(['name' => '__commonParams'])->exists($this->db)) {
            $this->db->createCommand()->update(
                $authItem,
                ['updated_at' => $now],
                ['name' => '__commonParams']
            )->execute();
        }
    }

    public function safeDown()
    {
        // No retirar grants en producción.
    }
}
