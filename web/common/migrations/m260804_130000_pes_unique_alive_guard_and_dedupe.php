<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * PES: unicidad real de (persona, efector, servicio) vigentes.
 *
 * El índice `ux_pes_persona_efector_servicio_alive` incluía `deleted_at`.
 * En MySQL/MariaDB un UNIQUE con NULL permite **varias filas activas**
 * (deleted_at IS NULL) con el mismo triple — el índice no las bloquea.
 *
 * Esta migración:
 * 1) soft-delete de duplicados activos (conserva el id menor)
 * 2) soft-delete de PES demo sandbox huérfanos (sesión ya purgada / sin sesión viva)
 * 3) reemplaza el índice por columna generada `alive_guard` (1 si vigente, NULL si baja)
 */
class m260804_130000_pes_unique_alive_guard_and_dedupe extends Migration
{
    public function safeUp(): void
    {
        $pes = '{{%profesional_efector_servicio}}';
        $schema = $this->db->schema->getTableSchema($pes, true);
        if ($schema === null) {
            echo "    > profesional_efector_servicio no existe; omitida.\n";

            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->dedupeActivePes($pes, $now);
        $this->softDeleteOrphanDemoSandboxPes($pes, $now);
        $this->replaceAliveUniqueIndex($pes, $schema);
    }

    public function safeDown(): void
    {
        $pes = '{{%profesional_efector_servicio}}';
        $schema = $this->db->schema->getTableSchema($pes, true);
        if ($schema === null) {
            return;
        }

        if (isset($schema->columns['alive_guard'])) {
            $this->dropIndexSafe($pes, 'ux_pes_persona_efector_servicio_alive');
            $this->dropColumn($pes, 'alive_guard');
            $this->createIndex(
                'ux_pes_persona_efector_servicio_alive',
                $pes,
                ['id_persona', 'id_efector', 'id_servicio', 'deleted_at'],
                true
            );
        }
    }

    private function dedupeActivePes(string $pes, string $now): void
    {
        $dupGroups = (new Query())
            ->from($pes)
            ->select(['id_persona', 'id_efector', 'id_servicio', 'cnt' => 'COUNT(*)', 'keep_id' => 'MIN(id)'])
            ->where(['deleted_at' => null])
            ->groupBy(['id_persona', 'id_efector', 'id_servicio'])
            ->having(['>', 'COUNT(*)', 1])
            ->all($this->db);

        $softDeleted = 0;
        foreach ($dupGroups as $g) {
            $keepId = (int) $g['keep_id'];
            $ids = (new Query())
                ->from($pes)
                ->select(['id'])
                ->where([
                    'id_persona' => (int) $g['id_persona'],
                    'id_efector' => (int) $g['id_efector'],
                    'id_servicio' => (int) $g['id_servicio'],
                    'deleted_at' => null,
                ])
                ->andWhere(['<>', 'id', $keepId])
                ->column($this->db);
            if ($ids === []) {
                continue;
            }
            $this->softDeletePesTree(array_map('intval', $ids), $now);
            $softDeleted += count($ids);
            echo "    > dedupe PES persona={$g['id_persona']} efector={$g['id_efector']} servicio={$g['id_servicio']}: keep={$keepId}, bajas=" . count($ids) . "\n";
        }
        if ($softDeleted === 0) {
            echo "    > sin duplicados activos PES (persona+efector+servicio).\n";
        }
    }

    private function softDeleteOrphanDemoSandboxPes(string $pes, string $now): void
    {
        $sessionTable = '{{%demo_sandbox_session}}';
        if ($this->db->schema->getTableSchema($sessionTable, true) === null) {
            return;
        }

        $aliveSessionPesIds = (new Query())
            ->from($sessionTable)
            ->select(['id_pes'])
            ->where(['purged_at' => null])
            ->andWhere(['>', 'id_pes', 0])
            ->column($this->db);
        $aliveSessionPesIds = array_values(array_unique(array_filter(array_map('intval', $aliveSessionPesIds))));

        // Personas staff demo: username demo_m_* o documento 38xxxxxx + nombre Demo.
        $demoPersonaIds = (new Query())
            ->from(['p' => '{{%personas}}'])
            ->select(['p.id_persona'])
            ->leftJoin(['u' => '{{%user}}'], 'u.id = p.id_user')
            ->where(['or',
                ['like', 'u.username', 'demo_m_%', false],
                ['and',
                    ['p.nombre' => 'Demo'],
                    ['p.apellido' => 'Médico'],
                    ['like', 'p.documento', '38%', false],
                ],
            ])
            ->column($this->db);
        $demoPersonaIds = array_values(array_unique(array_filter(array_map('intval', $demoPersonaIds))));
        if ($demoPersonaIds === []) {
            echo "    > sin personas demo sandbox para limpiar.\n";

            return;
        }

        $q = (new Query())
            ->from($pes)
            ->select(['id'])
            ->where(['deleted_at' => null])
            ->andWhere(['id_persona' => $demoPersonaIds]);
        if ($aliveSessionPesIds !== []) {
            $q->andWhere(['not in', 'id', $aliveSessionPesIds]);
        }
        $orphanIds = array_map('intval', $q->column($this->db));
        if ($orphanIds === []) {
            echo "    > sin PES demo huérfanos activos.\n";

            return;
        }

        $this->softDeletePesTree($orphanIds, $now);
        echo '    > soft-delete PES demo huérfanos: ' . count($orphanIds) . " (ids: " . implode(',', $orphanIds) . ")\n";
    }

    /**
     * @param list<int> $pesIds
     */
    private function softDeletePesTree(array $pesIds, string $now): void
    {
        if ($pesIds === []) {
            return;
        }
        $agenda = '{{%profesional_efector_servicio_agenda}}';
        if ($this->db->schema->getTableSchema($agenda, true) !== null) {
            $this->db->createCommand()->update(
                $agenda,
                ['deleted_at' => $now],
                ['and', ['id_profesional_efector_servicio' => $pesIds], ['deleted_at' => null]]
            )->execute();
        }
        $this->db->createCommand()->update(
            '{{%profesional_efector_servicio}}',
            ['deleted_at' => $now],
            ['and', ['id' => $pesIds], ['deleted_at' => null]]
        )->execute();
    }

    /**
     * @param \yii\db\TableSchema $schema
     */
    private function replaceAliveUniqueIndex(string $pes, $schema): void
    {
        $this->dropIndexSafe($pes, 'ux_pes_persona_efector_servicio_alive');

        if (!isset($schema->columns['alive_guard'])) {
            // 1 = vigente (única); NULL = baja (varias permitidas en UNIQUE MySQL).
            $this->execute(
                'ALTER TABLE ' . $this->db->quoteTableName($pes)
                . ' ADD COLUMN `alive_guard` TINYINT GENERATED ALWAYS AS '
                . '(IF(`deleted_at` IS NULL, 1, NULL)) VIRTUAL'
            );
        }

        $this->createIndex(
            'ux_pes_persona_efector_servicio_alive',
            $pes,
            ['id_persona', 'id_efector', 'id_servicio', 'alive_guard'],
            true
        );
        echo "    > índice único vigente vía alive_guard.\n";
    }

    private function dropIndexSafe(string $table, string $index): void
    {
        $raw = $this->db->schema->getRawTableName($table);
        $exists = (new Query())
            ->from('information_schema.statistics')
            ->where([
                'table_schema' => new \yii\db\Expression('DATABASE()'),
                'table_name' => $raw,
                'index_name' => $index,
            ])
            ->exists($this->db);
        if ($exists) {
            $this->dropIndex($index, $table);
        }
    }
}
