<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Medico: no edita a terceros ni cierra guardia desde atajos.
 * «Paciente se retiró» es CTA del tablero; horarios de plantel de otro van unificados en mis horarios.
 */
class m260814_170000_medico_revoke_staff_and_egreso_shortcuts extends Migration
{
    private const ROLE = 'Medico';

    /** @var list<string> */
    private const REVOKE_INTENT_IDS = [
        'urgencias.egreso-estructurado-flow',
        'profesional-cobertura.gestionar-staff',
        'profesional-agenda.configurar-staff',
        'profesional-identidad.editar-staff',
        'condicion-laboral.editar-staff',
        'profesionales.conteo-efector',
        'profesionales.listado-efector',
        'profesionales.distribucion-servicio-efector',
        'servicio-teleconsulta.configurar-efector-flow',
        'licencia.cargar-para-profesional-flow',
        'profesional-efector-servicio.crear-flow',
        'profesional-efector-servicio.baja-flow',
    ];

    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        foreach (self::REVOKE_INTENT_IDS as $intentId) {
            $this->db->createCommand()->delete($childTable, [
                'parent' => self::ROLE,
                'child' => $intentId,
            ])->execute();
        }

        if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
            \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
        }
    }

    public function safeDown(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        foreach (self::REVOKE_INTENT_IDS as $intentId) {
            if (!(new Query())->from($authItem)->where(['name' => $intentId])->exists($this->db)) {
                continue;
            }
            $exists = (new Query())
                ->from($childTable)
                ->where(['parent' => self::ROLE, 'child' => $intentId])
                ->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => self::ROLE,
                'child' => $intentId,
            ])->execute();
        }

        if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
            \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
        }
    }
}
