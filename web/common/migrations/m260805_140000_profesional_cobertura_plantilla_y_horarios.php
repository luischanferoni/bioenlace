<?php

use common\components\Platform\Infra\Migration\MigrationEnumColumn;
use common\models\Clinical\Encounter;
use yii\db\Migration;
use yii\db\Query;

/**
 * Plantilla semanal de cobertura EMER/IMP + materialización a profesional_cobertura.
 * RBAC: elegir-encounter-class + intent profesional-horarios.gestionar-propio.
 */
class m260805_140000_profesional_cobertura_plantilla_y_horarios extends Migration
{
    private const ROUTE_TYPE = 3;
    private const PERMISSION_TYPE = 2;

    private const ROUTE_ELEGIR_CLASE = '/api/profesional-cobertura/elegir-encounter-class';
    private const INTENT_HORARIOS = 'profesional-horarios.gestionar-propio';
    private const INHERIT_ROUTE_FROM = '/api/profesional-cobertura/gestionar';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $this->createPlantillaTable();
        $this->ensureRbac();
        $this->flushIntentCatalogCache();
        $this->bumpRbacRevision();
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $all = [self::ROUTE_ELEGIR_CLASE, self::INTENT_HORARIOS];
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => $all])->execute();
            $this->db->createCommand()->delete($childTable, ['parent' => self::INTENT_HORARIOS])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => $all])->execute();
        }

        $table = '{{%profesional_cobertura_plantilla}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            $this->dropTable($table);
        }
        $this->bumpRbacRevision();
    }

    private function createPlantillaTable(): void
    {
        $table = '{{%profesional_cobertura_plantilla}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey()->unsigned(),
            'id_persona' => $this->integer()->notNull(),
            'id_efector' => $this->integer()->notNull(),
            'id_servicio' => $this->integer()->null(),
            'id_profesional_efector_servicio' => $this->integer()->null(),
            'encounter_class' => MigrationEnumColumn::mysqlEnum(
                [Encounter::ENCOUNTER_CLASS_EMER, Encounter::ENCOUNTER_CLASS_IMP],
                Encounter::ENCOUNTER_CLASS_EMER,
                true,
                'EMER|IMP'
            ),
            'vigente_desde' => $this->date()->notNull(),
            'semanas' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(4),
            'lunes_2' => $this->string(128)->null(),
            'martes_2' => $this->string(128)->null(),
            'miercoles_2' => $this->string(128)->null(),
            'jueves_2' => $this->string(128)->null(),
            'viernes_2' => $this->string(128)->null(),
            'sabado_2' => $this->string(128)->null(),
            'domingo_2' => $this->string(128)->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->null(),
            'deleted_at' => $this->dateTime()->null(),
            'created_by' => $this->integer()->null(),
            'updated_by' => $this->integer()->null(),
            'deleted_by' => $this->integer()->null(),
        ]);

        $this->createIndex(
            'idx_pc_plantilla_persona_efector_clase',
            $table,
            ['id_persona', 'id_efector', 'encounter_class', 'id_profesional_efector_servicio']
        );
    }

    private function ensureRbac(): void
    {
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $now = time();
        $this->ensureItem($authItem, self::ROUTE_ELEGIR_CLASE, self::ROUTE_TYPE, 'API elegir clase horarios', $now);
        $this->ensureItem($authItem, self::INTENT_HORARIOS, self::PERMISSION_TYPE, 'Intent ' . self::INTENT_HORARIOS, $now);

        $this->inheritFrom($childTable, self::INHERIT_ROUTE_FROM, self::ROUTE_ELEGIR_CLASE);
        $this->linkPermissionToRoute($childTable, self::INTENT_HORARIOS, self::ROUTE_ELEGIR_CLASE);
        $this->linkPermissionToRoute($childTable, self::INTENT_HORARIOS, self::INHERIT_ROUTE_FROM);
        $this->linkPermissionToRoute($childTable, self::INTENT_HORARIOS, '/api/profesional-cobertura/elegir-pes');
        $this->linkPermissionToRoute($childTable, self::INTENT_HORARIOS, '/api/profesional-agenda/configurar-agenda');
        $this->linkPermissionToRoute($childTable, self::INTENT_HORARIOS, '/api/profesional-efector-servicio/listar-mis-servicios-en-efector');

        foreach ([
            'profesional-cobertura.gestionar-propio',
            'profesional-agenda.configurar-propio',
            'data-access.editar',
        ] as $source) {
            $this->migrateRoleGrants($childTable, $source, self::INTENT_HORARIOS);
        }
        foreach (['profesional-cobertura.gestionar-propio', 'profesional-cobertura.gestionar-staff'] as $perm) {
            $this->linkPermissionToRoute($childTable, $perm, self::ROUTE_ELEGIR_CLASE);
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

    private function inheritFrom(string $childTable, string $parentRoute, string $childRoute): void
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
                'child' => $childRoute,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $childRoute,
            ])->execute();
        }
    }

    private function migrateRoleGrants(string $childTable, string $source, string $intentId): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $source])
            ->column($this->db);
        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            // Evitar self-loop: el intent ya es parent de la ruta `$source`.
            if ($parent === $intentId) {
                continue;
            }
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => $intentId,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $intentId,
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

    private function flushIntentCatalogCache(): void
    {
        try {
            if (!class_exists(\Yii::class, false) || !\Yii::$app || !\Yii::$app->has('cache')) {
                return;
            }
            $cache = \Yii::$app->cache;
            if ($cache === null) {
                return;
            }
            $cache->delete('yaml_intents_catalog_v6');
            $cache->delete('yaml_intents_catalog_v7');
        } catch (\Throwable $e) {
        }
    }

    private function bumpRbacRevision(): void
    {
        try {
            if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
                \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
            }
        } catch (\Throwable $e) {
        }
    }
}
