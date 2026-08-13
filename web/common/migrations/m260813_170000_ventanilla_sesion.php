<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Tabla de sesión de mostrador + RBAC (hereda de buscar-persona; turnos como-paciente al rol Administrativo).
 */
class m260813_170000_ventanilla_sesion extends Migration
{
    private const ROUTE_TYPE = 3;

    private const INHERIT_FROM = '/personas/buscar-persona';

    private const ROLE = 'Administrativo';

    private const TABLE = '{{%ventanilla_sesion}}';

    /** @var list<string> */
    private const ROUTES = [
        '/api/ventanilla-sesion/iniciar',
        '/api/ventanilla-sesion/estado',
        '/api/ventanilla-sesion/cerrar',
        '/api/ventanilla-sesion/buscar-persona',
    ];

    /** @var list<string> */
    private const ADMIN_GRANTS = [
        'turnos.crear-como-paciente',
        'turnos.ver-mis-turnos-como-paciente',
        '/api/turnos/crear-como-paciente',
        '/api/turnos/listar-como-paciente',
    ];

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            $this->createTable(self::TABLE, [
                'id' => $this->primaryKey(),
                'staff_user_id' => $this->integer()->notNull(),
                'staff_persona_id' => $this->integer()->notNull(),
                'subject_persona_id' => $this->integer()->notNull(),
                'id_efector' => $this->integer()->notNull(),
                'identity_method' => $this->string(16)->notNull(),
                'started_at' => $this->dateTime()->notNull(),
                'expires_at' => $this->dateTime()->notNull(),
                'closed_at' => $this->dateTime()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex(
                'ix_ventanilla_staff_open',
                self::TABLE,
                ['staff_user_id', 'closed_at', 'expires_at']
            );
            $this->createIndex('ix_ventanilla_subject', self::TABLE, 'subject_persona_id');
        }

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
            $this->ensureItem($authItem, $route, self::ROUTE_TYPE, 'API sesión de ventanilla', $now);
            $this->inheritFrom($childTable, $authItem, self::INHERIT_FROM, $route);
        }

        if ((new Query())->from($authItem)->where(['name' => self::ROLE])->exists($this->db)) {
            foreach (self::ADMIN_GRANTS as $child) {
                if (!(new Query())->from($authItem)->where(['name' => $child])->exists($this->db)) {
                    continue;
                }
                $this->ensureChild($childTable, self::ROLE, $child);
            }
        }

        if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
            \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
        }
    }

    public function safeDown()
    {
        if (in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
            $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
            if ($this->db->schema->getTableSchema($childTable, true) !== null) {
                $this->db->createCommand()->delete($childTable, ['child' => self::ROUTES])->execute();
                foreach (self::ADMIN_GRANTS as $child) {
                    $this->db->createCommand()->delete($childTable, [
                        'parent' => self::ROLE,
                        'child' => $child,
                    ])->execute();
                }
            }
            if ($this->db->schema->getTableSchema($authItem, true) !== null) {
                $this->db->createCommand()->delete($authItem, ['name' => self::ROUTES])->execute();
            }
        }

        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            $this->dropTable(self::TABLE);
        }
    }

    private function ensureItem(string $authItem, string $name, int $type, string $description, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $name,
            'type' => $type,
            'description' => $description,
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function ensureChild(string $childTable, string $parent, string $child): void
    {
        if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => $child])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($childTable, [
            'parent' => $parent,
            'child' => $child,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $authItem, string $parentRoute, string $childRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        if ($parents === []) {
            $parents = (new Query())
                ->select('name')
                ->from($authItem)
                ->where(['name' => $parentRoute, 'type' => self::ROUTE_TYPE])
                ->column($this->db);
        }

        foreach ($parents as $parent) {
            $this->ensureChild($childTable, (string) $parent, $childRoute);
        }
    }
}
