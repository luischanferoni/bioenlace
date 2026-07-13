<?php

use yii\db\Migration;

/**
 * Prefijo geo_ para catálogo territorial: provincias, departamentos, localidades, barrios.
 */
class m260713_115000_rename_geo_tables_prefix extends Migration
{
    /** @var array<string, string> old => new */
    private const TABLES = [
        'provincias' => 'geo_provincias',
        'departamentos' => 'geo_departamentos',
        'localidades' => 'geo_localidades',
        'barrios' => 'geo_barrios',
    ];

    public function safeUp()
    {
        foreach (self::TABLES as $from => $to) {
            $this->renameIfNeeded($from, $to);
        }

        return true;
    }

    public function safeDown()
    {
        foreach (array_reverse(self::TABLES, true) as $from => $to) {
            $this->renameIfNeeded($to, $from);
        }

        return true;
    }

    private function renameIfNeeded(string $from, string $to): void
    {
        $schema = $this->db->schema;
        $fromExists = $schema->getTableSchema('{{%' . $from . '}}', true) !== null;
        $toExists = $schema->getTableSchema('{{%' . $to . '}}', true) !== null;

        if ($toExists) {
            echo "    > {{%{$to}}} ya existe; omitir rename desde {{%{$from}}}.\n";

            return;
        }
        if (!$fromExists) {
            echo "    > {{%{$from}}} no existe; omitir rename a {{%{$to}}}.\n";

            return;
        }

        $this->renameTable('{{%' . $from . '}}', '{{%' . $to . '}}');
        echo "    > Renombrada {{%{$from}}} → {{%{$to}}}.\n";
    }
}
