<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Quita el HTML legacy del snapshot de análisis en encounter_capture.
 * El contrato del pipeline es capture_review; el markup no se persiste ni se expone.
 */
class m260724_180000_strip_html_from_encounter_capture_analysis extends Migration
{
    private string $table = '{{%encounter_capture}}';

    public function safeUp(): void
    {
        $schema = $this->db->schema->getTableSchema($this->table, true);
        if ($schema === null || !isset($schema->columns['analysis_response_json'])) {
            return;
        }

        $rows = (new Query())
            ->from($this->table)
            ->select(['id', 'analysis_response_json'])
            ->where(['not', ['analysis_response_json' => null]])
            ->andWhere(['<>', 'analysis_response_json', ''])
            ->all($this->db);

        foreach ($rows as $row) {
            $raw = (string) ($row['analysis_response_json'] ?? '');
            if ($raw === '' || strpos($raw, '"html"') === false) {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || !array_key_exists('html', $decoded)) {
                continue;
            }

            unset($decoded['html']);
            $this->update(
                $this->table,
                [
                    'analysis_response_json' => json_encode(
                        $decoded,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                ],
                ['id' => (int) $row['id']]
            );
        }
    }

    public function safeDown(): void
    {
        // Irreversible: el HTML legacy no se reconstruye.
    }
}
