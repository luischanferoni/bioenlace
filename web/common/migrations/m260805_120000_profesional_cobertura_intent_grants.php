<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Grants de intents profesional-cobertura.* a roles que ya tienen agenda configurar
 * (el permiso intent ya existía en auth_item → IntentAccessService exige grant explícito).
 */
class m260805_120000_profesional_cobertura_intent_grants extends Migration
{
    private const PERMISSION_TYPE = 2;

    /** @var array<string, string> source intent → cobertura intent */
    private const SOURCE_TO_INTENT = [
        'profesional-agenda.configurar-propio' => 'profesional-cobertura.gestionar-propio',
        'profesional-agenda.configurar-staff' => 'profesional-cobertura.gestionar-staff',
        'data-access.editar' => 'profesional-cobertura.gestionar-propio',
        'DataAccess.edit' => 'profesional-cobertura.gestionar-propio',
    ];

    /** También staff desde data-access (además de propio). */
    private const ALSO_GRANT_STAFF_FROM = [
        'data-access.editar',
        'DataAccess.edit',
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
        foreach (['profesional-cobertura.gestionar-propio', 'profesional-cobertura.gestionar-staff'] as $intentId) {
            $this->ensurePermission($authItem, $intentId, $now);
        }

        foreach (self::SOURCE_TO_INTENT as $source => $intentId) {
            $this->migrateRoleGrants($childTable, $source, $intentId);
        }
        foreach (self::ALSO_GRANT_STAFF_FROM as $source) {
            $this->migrateRoleGrants($childTable, $source, 'profesional-cobertura.gestionar-staff');
        }

        // Roles que ya tenían rutas de cobertura (heredadas de agenda) pero sin intent.
        $this->migrateRoleGrants($childTable, '/api/profesional-cobertura/gestionar', 'profesional-cobertura.gestionar-propio');
        $this->migrateRoleGrants($childTable, '/api/profesional-cobertura/crear', 'profesional-cobertura.gestionar-propio');
        $this->migrateRoleGrants($childTable, '/api/profesional-cobertura/crear-para-recurso', 'profesional-cobertura.gestionar-staff');

        $this->flushIntentCatalogCache();
        $this->bumpRbacRevision();
    }

    public function safeDown()
    {
        // No revocar grants: pueden haberse asignado también a mano.
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
            // Migración no debe fallar por cache.
        }
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
