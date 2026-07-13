<?php

use yii\db\Migration;

/**
 * Prefijo cat_ para catálogos maestros de dominio (tipos, nomencladores).
 */
class m260713_140000_rename_cat_tables_prefix extends Migration
{
    /** @var array<string, string> old => new */
    private const TABLES = [
        'estado_civil' => 'cat_estado_civil',
        'tipo_telefono' => 'cat_tipo_telefono',
        'tipos_documentos' => 'cat_tipos_documentos',
        'tipo_consulta' => 'cat_tipo_consulta',
        'tipo_ingreso' => 'cat_tipo_ingreso',
        'estado_solicitud' => 'cat_estado_solicitud',
        'especialidades' => 'cat_especialidades',
        'profesiones' => 'cat_profesiones',
        'categorias_practicas' => 'cat_categorias_practicas',
        'motivos_derivacion' => 'cat_motivos_derivacion',
        'cobertura_medica' => 'cat_cobertura_medica',
        'condiciones_laborales' => 'cat_condiciones_laborales',
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
