<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: preview de impacto al configurar agenda hereda de configurar-agenda.
 *
 * En dumps (p. ej. u257309594) existían auth_item de preview sin auth_item_child → 403.
 */
class m260713_210000_api_profesional_agenda_preview_configurar_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const SOURCE_ROUTE = '/api/profesional-agenda/configurar-agenda';

    /** @var list<string> */
    private const PREVIEW_ROUTES = [
        '/api/profesional-agenda/preview-configurar-agenda',
        '/api/profesional-agenda/preview-impacto-agenda',
    ];

    /** @var list<string> */
    private const INTENT_IDS = [
        'profesional-agenda.configurar-propio',
        'profesional-agenda.configurar-staff',
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
        foreach (self::PREVIEW_ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            $this->inheritFrom($childTable, self::SOURCE_ROUTE, $route);
            foreach (self::INTENT_IDS as $intentId) {
                if ((new Query())->from($authItem)->where(['name' => $intentId])->exists($this->db)) {
                    $this->linkPermissionToRoute($childTable, $intentId, $route);
                }
            }
        }

        $this->bumpRbacRevision();
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => self::PREVIEW_ROUTES])->execute();
        }

        $this->bumpRbacRevision();
    }

    private function ensureRoute(string $authItem, string $name, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }

        $row = [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API preview impacto configurar agenda',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($this->columnExists($authItem, 'group_code')) {
            $parentGroup = (new Query())
                ->select('group_code')
                ->from($authItem)
                ->where(['name' => self::SOURCE_ROUTE])
                ->scalar($this->db);
            if (is_string($parentGroup) && $parentGroup !== '') {
                $row['group_code'] = $parentGroup;
            }
        }
        $this->db->createCommand()->insert($authItem, $row)->execute();
    }

    private function inheritFrom(string $childTable, string $parentRoute, string $newRoute): void
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
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => $newRoute,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $newRoute,
            ])->execute();
        }

        if (!(new Query())->from($childTable)->where([
            'parent' => $parentRoute,
            'child' => $newRoute,
        ])->exists($this->db)) {
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parentRoute,
                'child' => $newRoute,
            ])->execute();
        }
    }

    private function linkPermissionToRoute(string $childTable, string $permission, string $route): void
    {
        if ((new Query())->from($childTable)->where([
            'parent' => $permission,
            'child' => $route,
        ])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($childTable, [
            'parent' => $permission,
            'child' => $route,
        ])->execute();
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }

    private function bumpRbacRevision(): void
    {
        try {
            if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
                \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
