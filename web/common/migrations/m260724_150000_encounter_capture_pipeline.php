<?php

use common\components\Platform\Infra\Migration\MigrationEnumColumn;
use common\models\Clinical\EncounterCapture;
use yii\db\Migration;
use yii\db\Query;

/**
 * Borrador durable de captura clínica por etapas (sync, sin jobs).
 */
class m260724_150000_encounter_capture_pipeline extends Migration
{
    private string $table = '{{%encounter_capture}}';

    private const ROUTE_TYPE = 3;

    /** @var list<string> */
    private const NEW_ROUTES = [
        '/api/clinical/encounter/captura-crear-o-subir',
        '/api/clinical/encounter/captura-transcribir',
        '/api/clinical/encounter/captura-analizar',
        '/api/clinical/encounter/captura-guardar',
        '/api/clinical/encounter/captura-listar',
        '/api/clinical/encounter/captura-ver',
        '/api/clinical/encounter/captura-descartar',
        '/api/clinical/encounter/captura-audio',
    ];

    /** @var list<string> */
    private const INHERIT_FROM = [
        '/api/clinical/encounter/analizar',
        '/api/consulta/analizar',
    ];

    public function safeUp(): void
    {
        $schema = $this->db->schema->getTableSchema($this->table, true);
        if ($schema === null) {
            $this->createTable($this->table, [
                'id' => $this->primaryKey(),
                'client_capture_id' => $this->string(64)->notNull(),
                'subject_persona_id' => $this->integer()->notNull(),
                'parent_type' => $this->string(32)->null(),
                'parent_id' => $this->integer()->null(),
                'encounter_id' => $this->integer()->null(),
                'created_by_user_id' => $this->integer()->notNull(),
                'stage' => MigrationEnumColumn::mysqlEnum(
                    EncounterCapture::stageValues(),
                    EncounterCapture::STAGE_UPLOADED,
                    true,
                    implode('|', EncounterCapture::stageValues())
                ),
                'audio_relative_path' => $this->string(512)->null(),
                'audio_mime' => $this->string(64)->null(),
                'transcript' => $this->text()->null(),
                'texto_procesado' => $this->text()->null(),
                'stt_meta_json' => $this->text()->null(),
                'datos_extraidos_json' => 'LONGTEXT NULL',
                'analysis_response_json' => 'LONGTEXT NULL',
                'analysis_cache_token' => $this->string(64)->null(),
                'staged_item_ids_json' => $this->text()->null(),
                'last_error' => $this->text()->null(),
                'attempts_stt' => $this->integer()->notNull()->defaultValue(0),
                'attempts_analysis' => $this->integer()->notNull()->defaultValue(0),
                'attempts_save' => $this->integer()->notNull()->defaultValue(0),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ux_encounter_capture_client_id', $this->table, 'client_capture_id', true);
            $this->createIndex(
                'idx_encounter_capture_ctx_stage',
                $this->table,
                ['subject_persona_id', 'parent_type', 'parent_id', 'stage']
            );
            $this->createIndex('idx_encounter_capture_creator', $this->table, ['created_by_user_id', 'updated_at']);
            $this->createIndex('idx_encounter_capture_encounter', $this->table, 'encounter_id');
        }

        $this->ensureRbacRoutes();
    }

    public function safeDown(): void
    {
        $schema = $this->db->schema->getTableSchema($this->table, true);
        if ($schema !== null) {
            $this->dropTable($this->table);
        }
    }

    private function ensureRbacRoutes(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        foreach (self::NEW_ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            if (!$hasChild) {
                continue;
            }
            foreach (self::INHERIT_FROM as $sourceRoute) {
                if (!(new Query())->from($authItem)->where(['name' => $sourceRoute])->exists($this->db)) {
                    continue;
                }
                $this->inheritFrom($childTable, $sourceRoute, $route);
            }
        }
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API clinical encounter captura pipeline',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $sourceRoute, string $targetRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $sourceRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => $targetRoute,
            ])->exists($this->db)) {
                continue;
            }

            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $targetRoute,
            ])->execute();
        }
    }
}
