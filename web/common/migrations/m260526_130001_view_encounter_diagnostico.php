<?php

use yii\db\Migration;

/**
 * Reemplaza `view_consulta_diagnostico` (join a `consultas`) por vista sobre `encounter`.
 *
 * Requiere tabla `encounter` y `diagnostico_consultas` (hasta drop legacy en m260520_100002).
 */
class m260526_130001_view_encounter_diagnostico extends Migration
{
    public function safeUp()
    {
        $this->execute('DROP VIEW IF EXISTS view_consulta_diagnostico');
        $this->execute('DROP VIEW IF EXISTS view_encounter_diagnostico');

        $parentClassCase = <<<'SQL'
CASE enc.parent_type
    WHEN 'TURNO' THEN '\\common\\models\\Turno'
    WHEN 'DERIVACION' THEN '\\common\\models\\ConsultaDerivaciones'
    WHEN 'INTERNACION' THEN '\\common\\models\\SegNivelInternacion'
    WHEN 'GENERICO_AMB' THEN '\\common\\models\\GenericoAMB'
    WHEN 'GENERICO_EMER' THEN '\\common\\models\\GenericoEMER'
    WHEN 'GUARDIA' THEN '\\common\\models\\Guardia'
    WHEN 'PASE_PREVIO' THEN '\\common\\models\\ServiciosEfector'
    WHEN 'ENCUESTA_PARCHES' THEN '\\common\\models\\EncuestaParchesMamarios'
    WHEN 'CIRUGIA' THEN '\\common\\models\\Cirugia'
    ELSE NULL
END
SQL;

        $sql = <<<SQL
CREATE VIEW view_encounter_diagnostico AS
SELECT
    dc.*,
    enc.subject_persona_id AS id_persona,
    enc.parent_id AS c_parent_id,
    {$parentClassCase} AS c_parent_class,
    enc.created_at AS c_created_at
FROM diagnostico_consultas dc
INNER JOIN encounter enc
    ON enc.id = dc.id_consulta
    AND enc.deleted_at IS NULL
SQL;

        $this->execute($sql);
    }

    public function safeDown()
    {
        $this->execute('DROP VIEW IF EXISTS view_encounter_diagnostico');
        echo "    > m260526_130001: safeDown no recrea view_consulta_diagnostico (legacy).\n";

        return true;
    }
}
