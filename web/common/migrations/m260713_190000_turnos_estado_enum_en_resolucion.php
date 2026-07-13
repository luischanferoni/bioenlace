<?php

use yii\db\Migration;

/**
 * Agrega EN_RESOLUCION al ENUM turnos.estado (faltaba en prod: MySQL guardaba '').
 * Repara turnos con resolución pendiente y estado vacío.
 */
class m260713_190000_turnos_estado_enum_en_resolucion extends Migration
{
    public function safeUp()
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }

        $this->execute(
            "ALTER TABLE {{%turnos}} MODIFY COLUMN `estado` ENUM(
                'PENDIENTE',
                'CANCELADO',
                'EN_ATENCION',
                'ATENDIDO',
                'SIN_ATENDER',
                'EN_RESOLUCION'
            ) NOT NULL DEFAULT 'PENDIENTE'"
        );

        // Tras ALTER, '' inválido → filas rotas por saves previos de EN_RESOLUCION.
        $this->execute(
            "UPDATE {{%turnos}} t
             INNER JOIN {{%turno_resolucion}} r
                ON r.id_turno = t.id_turnos AND r.estado = 'pendiente'
             SET t.estado = 'EN_RESOLUCION'
             WHERE t.estado = '' OR t.estado IS NULL"
        );

        $this->execute(
            "UPDATE {{%turnos}}
             SET estado = 'PENDIENTE'
             WHERE estado = '' OR estado IS NULL"
        );

        $this->db->schema->refreshTableSchema('{{%turnos}}');
    }

    public function safeDown()
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }

        $this->execute(
            "UPDATE {{%turnos}} SET estado = 'PENDIENTE' WHERE estado = 'EN_RESOLUCION'"
        );

        $this->execute(
            "ALTER TABLE {{%turnos}} MODIFY COLUMN `estado` ENUM(
                'PENDIENTE',
                'CANCELADO',
                'EN_ATENCION',
                'ATENDIDO',
                'SIN_ATENDER'
            ) NOT NULL DEFAULT 'PENDIENTE'"
        );

        $this->db->schema->refreshTableSchema('{{%turnos}}');
    }
}
