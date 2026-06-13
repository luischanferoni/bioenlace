<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Retira rutas API deprecated (sin clientes legacy): flujos agenda editar-* y efectores/elegir*.
 */
class m260628_100000_retire_deprecated_api_routes_rbac extends Migration
{
    /** @var list<string> */
    private const DEPRECATED_ROUTES = [
        '/api/profesional-agenda/editar-flow',
        '/api/profesional-agenda/editar-mi-flow',
        '/api/profesional-agenda/editar-agenda-flow',
        '/api/profesional-agenda/editar-mi-agenda-flow',
        '/api/efectores/elegir',
        '/api/efectores/elegir-nearby',
    ];

    /** @var array<string, string> permiso webvimark → ruta canónica */
    private const PERMISSION_ROUTE_RELINK = [
        'listadoEfectores' => '/api/efectores/listar-por-servicio',
        'editar_mi_agenda' => '/api/editar',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260628_100000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $itemTable = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null
            || $this->db->schema->getTableSchema($itemTable, true) === null) {
            echo "m260628_100000: sin tablas RBAC, omitido.\n";

            return;
        }

        $removedLinks = (int) $this->db->createCommand()->delete($childTable, [
            'or',
            ['parent' => self::DEPRECATED_ROUTES],
            ['child' => self::DEPRECATED_ROUTES],
        ])->execute();

        $removedRoutes = (int) $this->db->createCommand()->delete($itemTable, [
            'and',
            ['type' => 3],
            ['name' => self::DEPRECATED_ROUTES],
        ])->execute();

        $relinked = 0;
        foreach (self::PERMISSION_ROUTE_RELINK as $permission => $canonicalRoute) {
            if (!(new Query())->from($itemTable)->where(['name' => $permission])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->delete($childTable, [
                'and',
                ['parent' => $permission],
                ['child' => self::DEPRECATED_ROUTES],
            ])->execute();
            if (!(new Query())->from($itemTable)->where(['name' => $canonicalRoute, 'type' => 3])->exists($this->db)) {
                $now = time();
                $this->db->createCommand()->insert($itemTable, [
                    'name' => $canonicalRoute,
                    'type' => 3,
                    'description' => null,
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
            if ((new Query())->from($childTable)->where(['parent' => $permission, 'child' => $canonicalRoute])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $permission,
                'child' => $canonicalRoute,
            ])->execute();
            $relinked++;
        }

        echo sprintf(
            "m260628_100000: enlaces=%d rutas=%d reenlaces_permiso=%d\n",
            $removedLinks,
            $removedRoutes,
            $relinked
        );
    }

    public function safeDown()
    {
        echo "m260628_100000: safeDown no restaura rutas deprecated.\n";
    }
}
