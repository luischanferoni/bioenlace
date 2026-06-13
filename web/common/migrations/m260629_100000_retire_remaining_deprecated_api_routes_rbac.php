<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Lote 2: rutas deprecated retiradas del código tras m260628 (pes/servicios/notificaciones elegir).
 * Idempotente: no-op si las rutas ya fueron eliminadas.
 */
class m260629_100000_retire_remaining_deprecated_api_routes_rbac extends Migration
{
    /** @var list<string> */
    private const DEPRECATED_ROUTES = [
        '/api/profesional-efector-servicio/elegir',
        '/api/servicios/elegir',
        '/api/notificaciones/listar-como-paciente',
        '/api/notificaciones/marcar-leida-como-paciente',
    ];

    /** @var array<string, list<string>> permiso webvimark → rutas canónicas */
    private const PERMISSION_ROUTES_RELINK = [
        'notificaciones_alertas_como_paciente' => [
            '/api/notificaciones/listar',
            '/api/notificaciones/marcar-leida',
        ],
        'listar_servicios_especialidades' => ['/api/servicios/elegir-acepta-turnos'],
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260629_100000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $itemTable = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null
            || $this->db->schema->getTableSchema($itemTable, true) === null) {
            echo "m260629_100000: sin tablas RBAC, omitido.\n";

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
        $now = time();
        foreach (self::PERMISSION_ROUTES_RELINK as $permission => $canonicalRoutes) {
            if (!(new Query())->from($itemTable)->where(['name' => $permission])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->delete($childTable, [
                'and',
                ['parent' => $permission],
                ['child' => self::DEPRECATED_ROUTES],
            ])->execute();
            foreach ($canonicalRoutes as $canonicalRoute) {
                if (!(new Query())->from($itemTable)->where(['name' => $canonicalRoute, 'type' => 3])->exists($this->db)) {
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
        }

        echo sprintf(
            "m260629_100000: enlaces=%d rutas=%d reenlaces_permiso=%d\n",
            $removedLinks,
            $removedRoutes,
            $relinked
        );
    }

    public function safeDown()
    {
        echo "m260629_100000: safeDown no restaura rutas deprecated.\n";
    }
}
