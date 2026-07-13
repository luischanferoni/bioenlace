<?php

use common\components\Domain\Person\Service\Seed\DepartamentosLocalidadesArgentinaSeedService;
use common\components\Domain\Person\Service\Seed\ProvinciasArgentinaSeedService;
use yii\db\Migration;

/**
 * Amplía cod_indec de departamentos, siembra catálogo Georef y reasigna efectores.
 *
 * - Departamentos/localidades: metadata gzip (INDEC/Georef).
 * - Efectores: localidad Capital SDE, salvo id=1509 → Capital Santa Fe.
 */
class m260713_120000_seed_departamentos_localidades_argentina extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%geo_departamentos}}', true) === null
            || $this->db->schema->getTableSchema('{{%geo_localidades}}', true) === null
        ) {
            echo "    > Tablas geográficas incompletas; omitir seed.\n";

            return true;
        }

        $this->widenDepartamentoCodIndec();
        $this->ensureLocalidadCodBahra();

        if ($this->db->schema->getTableSchema('{{%geo_provincias}}', true) !== null) {
            $provincias = (new ProvinciasArgentinaSeedService())->upsertAll();
            echo sprintf(
                "    > Provincias: %d catálogo, %d insertadas, %d actualizadas.\n",
                $provincias['total'],
                $provincias['inserted'],
                $provincias['updated']
            );
        }

        $geo = new DepartamentosLocalidadesArgentinaSeedService();
        $result = $geo->upsertAll();
        echo sprintf(
            "    > Departamentos: %d total (%d ins, %d upd). Localidades: %d total (%d ins, %d upd).\n",
            $result['total_departamentos'],
            $result['departamentos_inserted'],
            $result['departamentos_updated'],
            $result['total_localidades'],
            $result['localidades_inserted'],
            $result['localidades_updated']
        );

        if ($this->db->schema->getTableSchema('{{%efectores}}', true) !== null) {
            $ef = $geo->reassignEfectoresToCapitales();
            echo sprintf(
                "    > Efectores: %d → SDE (loc %d), %d → Santa Fe demo (loc %d).\n",
                $ef['actualizados_sde'],
                $ef['santiago_localidad'],
                $ef['actualizados_sf'],
                $ef['santa_fe_localidad']
            );
        }

        return true;
    }

    public function safeDown()
    {
        echo "    > safeDown no elimina catálogo geográfico ni revierte efectores.\n";

        return true;
    }

    private function widenDepartamentoCodIndec(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%geo_departamentos}}', true);
        if ($schema === null || !isset($schema->columns['cod_indec'])) {
            return;
        }
        $size = (int) ($schema->columns['cod_indec']->size ?? 0);
        if ($size > 0 && $size < 5) {
            $this->alterColumn('{{%geo_departamentos}}', 'cod_indec', $this->string(5)->notNull());
            echo "    > geo_departamentos.cod_indec ampliado a varchar(5).\n";
        }
    }

    private function ensureLocalidadCodBahra(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%geo_localidades}}', true);
        if ($schema === null) {
            return;
        }
        if (!isset($schema->columns['cod_bahra'])) {
            $this->addColumn('{{%geo_localidades}}', 'cod_bahra', $this->string(15)->null());
            echo "    > geo_localidades.cod_bahra agregada.\n";
        }
        $this->createIndexIfMissing('{{%geo_localidades}}', 'idx_geo_localidades_cod_bahra', ['cod_bahra']);
    }

    /**
     * @param string|string[] $columns
     */
    private function createIndexIfMissing(string $table, string $name, $columns): void
    {
        $db = $this->db;
        $tableSchema = $db->schema->getTableSchema($table, true);
        if ($tableSchema === null) {
            return;
        }
        if (isset($tableSchema->indexes[$name])) {
            return;
        }
        // MySQL: índices pueden no estar en TableSchema::indexes según driver; intentar CREATE.
        try {
            $this->createIndex($name, $table, $columns);
            echo "    > Índice {$name} creado.\n";
        } catch (\Throwable $e) {
            echo "    > Índice {$name}: " . $e->getMessage() . "\n";
        }
    }
}
