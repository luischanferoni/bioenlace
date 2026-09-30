<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * El artículo de representación no matcheaba "amigo" ni el tag "tercero" del preprocess.
 */
class m260930_120000_info_content_representacion_tercero_keywords extends Migration
{
    private const TABLE = '{{%info_content_article}}';

    /** @var list<string> */
    private const EXTRA = ['amigo', 'amiga', 'tercero', 'otra persona', 'otra_persona'];

    public function safeUp(): void
    {
        $this->appendKeywords();
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        $row = (new Query())
            ->from(self::TABLE)
            ->where(['topic' => 'representacion', 'scope' => 'producto'])
            ->one($this->db);
        if (!is_array($row)) {
            return;
        }

        $drop = array_fill_keys(self::EXTRA, true);
        $kept = [];
        foreach ($this->split((string) ($row['keywords'] ?? '')) as $keyword) {
            if (!isset($drop[mb_strtolower($keyword)])) {
                $kept[] = $keyword;
            }
        }

        $this->update(self::TABLE, [
            'keywords' => implode(',', $kept),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $row['id']]);
    }

    private function appendKeywords(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        $row = (new Query())
            ->from(self::TABLE)
            ->where(['topic' => 'representacion', 'scope' => 'producto'])
            ->one($this->db);
        if (!is_array($row)) {
            return;
        }

        $keywords = $this->split((string) ($row['keywords'] ?? ''));
        $folded = [];
        foreach ($keywords as $keyword) {
            $folded[mb_strtolower($keyword)] = true;
        }
        foreach (self::EXTRA as $extra) {
            if (!isset($folded[$extra])) {
                $keywords[] = $extra;
                $folded[$extra] = true;
            }
        }

        $joined = implode(',', $keywords);
        if (strlen($joined) > 500) {
            echo "keywords de representacion superan 500 caracteres; no se actualiza.\n";

            return;
        }

        $this->update(self::TABLE, [
            'keywords' => $joined,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $row['id']]);
    }

    /**
     * @return list<string>
     */
    private function split(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
    }
}
