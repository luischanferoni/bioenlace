<?php

use yii\db\Migration;

/**
 * Prefijo persona_ unificado para datos de persona (domicilio, HC, antecedentes).
 *
 * - domicilios → persona_domicilios (entidad dirección)
 * - personas_domicilios → persona_domicilios_vinculo (N:M persona↔domicilio)
 * - personas_hc → persona_hc
 * - personas_antecedentes → persona_antecedentes
 *
 * Ya tenían persona_: persona_mails, persona_telefono (sin cambio).
 */
class m260713_150000_rename_persona_tables_prefix extends Migration
{
    /** @var array<string, string> old => new */
    private const TABLES = [
        'domicilios' => 'persona_domicilios',
        'personas_domicilios' => 'persona_domicilios_vinculo',
        'personas_hc' => 'persona_hc',
        'personas_antecedentes' => 'persona_antecedentes',
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
