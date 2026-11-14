<?php

use yii\db\Migration;

/**
 * Extensión de `observation` para paneles clínicos por especialidad.
 *
 * Una observación puede pertenecer a un panel (un control prenatal, una sesión de
 * salud mental). El panel agrupa por `panel_kind` + `panel_id` y guarda el campo que
 * representa, para reconstruirlo y graficar su evolución.
 */
class m261114_090000_create_observation_panel_ext extends Migration
{
    private const TABLE = '{{%observation_panel_ext}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            return;
        }

        $observation = $this->db->schema->getTableSchema('{{%observation}}', true);
        $idColumn = ($observation !== null && isset($observation->columns['id']))
            ? $this->integer()->notNull()->comment('observation.id')
            : $this->integer()->notNull();

        $this->createTable(self::TABLE, [
            'observation_id' => $idColumn,
            'panel_kind' => $this->string(32)->notNull()->comment('obstetrics|mental-health|…'),
            'panel_id' => $this->integer()->notNull()->comment('Agrupador del panel'),
            'field_key' => $this->string(48)->notNull()->comment('Campo del panel'),
        ]);

        $this->addPrimaryKey('pk_observation_panel_ext', self::TABLE, 'observation_id');
        $this->createIndex(
            'idx_observation_panel_kind_id',
            self::TABLE,
            ['panel_kind', 'panel_id']
        );

        if ($observation !== null) {
            $this->addForeignKey(
                'fk_observation_panel_observation',
                self::TABLE,
                'observation_id',
                '{{%observation}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            $this->dropTable(self::TABLE);
        }
    }
}
