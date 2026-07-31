<?php

use yii\db\Migration;

/**
 * Comentarios de tabla: servicio = oferta institucional del efector; PES = asignación a esa oferta.
 */
class m260731_110000_comment_servicios_pes_institucional extends Migration
{
    public function safeUp(): void
    {
        $this->commentTableIfExists(
            '{{%servicios}}',
            'Oferta asistencial del establecimiento (HealthcareService). '
            . 'No es la especialidad del titulo del profesional ni un acto/practica SNOMED. '
            . 'PES (profesional_efector_servicio) asigna un profesional a este servicio en un efector. '
            . 'Ver docs/producto/glosario-servicio-pes-acto.md'
        );
        $this->commentTableIfExists(
            '{{%profesional_efector_servicio}}',
            'Asignacion operacional persona+efector+servicio institucional del centro. '
            . 'No define el acto clinico (SNOMED); el acto va en service_request.code u ordenes. '
            . 'Ver docs/producto/glosario-servicio-pes-acto.md'
        );
        $this->commentTableIfExists(
            '{{%actos_clinicos}}',
            'Cache/catalogo de actos clinicos codificados (SNOMED/LOINC). '
            . 'No reemplaza Snowstorm como fuente de verdad; no es una fila de servicios.'
        );
        $this->commentTableIfExists(
            '{{%linea_acto}}',
            'Puente capacidad: servicio institucional (servicios) <-> acto clinico. '
            . 'Excepciones o vinculos explicitos; la regla general puede vivir en metadata ECL.'
        );
    }

    public function safeDown(): void
    {
        $this->commentTableIfExists('{{%servicios}}', '');
        $this->commentTableIfExists('{{%profesional_efector_servicio}}', '');
        $this->commentTableIfExists('{{%actos_clinicos}}', '');
        $this->commentTableIfExists('{{%linea_acto}}', '');
    }

    private function commentTableIfExists(string $table, string $comment): void
    {
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }
        // MySQL / MariaDB
        $raw = $this->db->schema->getRawTableName($table);
        $escaped = addslashes($comment);
        $this->execute("ALTER TABLE `{$raw}` COMMENT = '{$escaped}'");
    }
}
