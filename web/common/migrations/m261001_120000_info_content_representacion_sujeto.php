<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * El artículo de representación explica para quién es lo que se hace en el sistema
 * y cómo se indica otra persona en el celular y en la web.
 */
class m261001_120000_info_content_representacion_sujeto extends Migration
{
    private const TABLE = '{{%info_content_article}}';

    private const PARAGRAPH = <<<'TXT'
Lo que se hace en el sistema es de la persona con la que se entró. Para hacerlo por otra persona, primero hay que indicar por quién se opera.

En el celular: Configuración → Representación. Ahí se vincula un menor o se designa un representante. Cuando ese vínculo está activo, la app pregunta «¿Por quién operás?». Si ya se opera por otra persona, una barra indica «A cargo de» seguido del nombre. A partir de ahí, lo que se pida es de esa persona.

En el sitio web se usa el control de representación de la sesión, cuando la pantalla lo muestra.
TXT;

    public function safeUp(): void
    {
        $row = $this->productoRow();
        if ($row === null) {
            return;
        }

        $body = (string) ($row['body'] ?? '');
        if (mb_strpos($body, '¿Por quién operás?') !== false) {
            return;
        }

        $this->update(self::TABLE, [
            'body' => rtrim($body) . "\n\n" . self::PARAGRAPH,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $row['id']]);
    }

    public function safeDown(): void
    {
        $row = $this->productoRow();
        if ($row === null) {
            return;
        }

        $body = (string) ($row['body'] ?? '');
        $trimmed = rtrim(str_replace("\n\n" . self::PARAGRAPH, '', $body));
        if ($trimmed === rtrim($body)) {
            return;
        }

        $this->update(self::TABLE, [
            'body' => $trimmed,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $row['id']]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function productoRow(): ?array
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return null;
        }

        $row = (new Query())
            ->from(self::TABLE)
            ->where(['topic' => 'representacion', 'scope' => 'producto'])
            ->one($this->db);

        return is_array($row) ? $row : null;
    }
}
