<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Normaliza efectores.origen_financiamiento a Público|Privado (sector paciente).
 * Valores jurisdiccionales (Nacional/Provincial/Municipal/…) pasan a Público
 * salvo Privado; "Publico" legacy → Público.
 */
class m260713_160000_normalize_efector_origen_financiamiento_sector extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%efectores}}', true) === null) {
            echo "    > Tabla efectores no existe; omitir.\n";

            return true;
        }

        $privado = (int) $this->db->createCommand()->update(
            '{{%efectores}}',
            ['origen_financiamiento' => 'Privado'],
            ['like', 'origen_financiamiento', 'Privado', false]
        )->execute();

        // Todo lo no-privado y no vacío que no sea ya "Público" exacto → Público
        // (incluye Provincial, Nacional, Municipal, Publico, etc.)
        $rows = (new Query())
            ->select(['id_efector', 'origen_financiamiento'])
            ->from('{{%efectores}}')
            ->all($this->db);

        $aPublico = 0;
        foreach ($rows as $row) {
            $origen = trim((string) ($row['origen_financiamiento'] ?? ''));
            if ($origen === '' || strcasecmp($origen, 'Privado') === 0) {
                continue;
            }
            if ($origen === 'Público') {
                continue;
            }
            // Publico sin tilde u otros valores públicos/jurisdiccionales
            $this->db->createCommand()->update(
                '{{%efectores}}',
                ['origen_financiamiento' => 'Público'],
                ['id_efector' => (int) $row['id_efector']]
            )->execute();
            $aPublico++;
        }

        echo sprintf(
            "    > origen_financiamiento: %d → Privado, %d → Público.\n",
            $privado,
            $aPublico
        );

        return true;
    }

    public function safeDown()
    {
        echo "    > safeDown no revierte normalización de origen_financiamiento.\n";

        return true;
    }
}
