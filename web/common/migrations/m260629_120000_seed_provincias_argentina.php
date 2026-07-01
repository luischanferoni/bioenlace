<?php

use common\components\Domain\Person\Service\Seed\ProvinciasArgentinaSeedService;
use yii\db\Migration;

/**
 * Seed idempotente: 24 jurisdicciones argentinas en {{%provincias}} (contexto paciente).
 *
 * Fuente: common/metadata/bioenlace/geo/provincias-argentina.yaml
 */
class m260629_120000_seed_provincias_argentina extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%provincias}}', true) === null) {
            echo "    > Tabla provincias no existe; omitir seed.\n";

            return true;
        }

        $result = (new ProvinciasArgentinaSeedService())->upsertAll();
        echo sprintf(
            "    > Provincias Argentina: %d filas en YAML, %d insertadas, %d actualizadas.\n",
            $result['total'],
            $result['inserted'],
            $result['updated']
        );

        return true;
    }

    public function safeDown()
    {
        echo "    > safeDown no elimina provincias (datos maestros compartidos).\n";

        return true;
    }
}
