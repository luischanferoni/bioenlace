<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Otorga al rol paciente las rutas de recordatorios de care plan.
 *
 * m260530 / m260531 solo creaban auth_item; la app móvil llama
 * GET /api/v1/clinical/care-plans/recordatorios-como-paciente sin intent header → 403.
 */
class m260716_100000_api_care_plan_reminder_paciente_grants extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PERMISSION_TYPE = 2;

    private const ROLE_PACIENTE = 'paciente';

    private const INTENT_ID = 'tratamiento.recordatorios-como-paciente';

    /** Quien ve planes activos puede configurar recordatorios. */
    private const API_PARENT_ROUTE = '/api/clinical/care-plan/active';

    /** @var list<string> */
    private const API_ROUTES = [
        '/api/clinical/care-plans/recordatorios-como-paciente',
        '/api/clinical/care-plan/recordatorios-como-paciente',
        '/api/clinical/care-plans/preferencias-recordatorios-como-paciente',
        '/api/clinical/care-plan/preferencias-recordatorios-como-paciente',
        '/api/clinical/care-plan/actualizar-preferencias-recordatorios-como-paciente',
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

        $this->ensurePermission($authItem, self::INTENT_ID, $now);

        foreach (self::API_ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            $this->inheritFrom($childTable, self::API_PARENT_ROUTE, $route);
            $this->ensureChild($childTable, self::ROLE_PACIENTE, $route);
        }

        $this->inheritFrom($childTable, self::API_PARENT_ROUTE, self::INTENT_ID);
        $this->ensureChild($childTable, self::ROLE_PACIENTE, self::INTENT_ID);
        $this->ensureChild(
            $childTable,
            self::INTENT_ID,
            '/api/clinical/care-plans/preferencias-recordatorios-como-paciente'
        );
        $this->ensureChild(
            $childTable,
            self::INTENT_ID,
            '/api/clinical/care-plans/recordatorios-como-paciente'
        );

        $this->bumpRbacRevision();
    }

    public function safeDown()
    {
        // No retirar grants ya otorgados en producción.
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API recordatorios care plan (paciente)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function ensurePermission(string $authItem, string $intentId, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $intentId])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $intentId,
            'type' => self::PERMISSION_TYPE,
            'description' => 'Intent ' . $intentId,
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $parentChild, string $newChild): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentChild])
            ->column($this->db);

        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            $this->ensureChild($childTable, $parent, $newChild);
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

    private function bumpRbacRevision(): void
    {
        try {
            if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
                \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
            }
        } catch (\Throwable $e) {
            // Migración no debe fallar por cache de revisión.
        }
    }
}
