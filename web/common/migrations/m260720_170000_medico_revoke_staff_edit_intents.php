<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Medico: quitar intents de edición/métricas de terceros (atajos «Para el personal»).
 *
 * En el dump `u257309594_bioenlace.sql` el rol Medico tenía grants de *-staff y
 * métricas de efector; el fallback por `rbac_route` compartida con *-propio
 * agravaba el filtrado. Ver también {@see IntentAccessService}.
 */
class m260720_170000_medico_revoke_staff_edit_intents extends Migration
{
    private const ROLE = 'Medico';

    /** @var list<string> */
    private const REVOKE_INTENT_IDS = [
        'profesional-agenda.configurar-staff',
        'profesional-identidad.editar-staff',
        'condicion-laboral.editar-staff',
        'licencia.cargar-para-profesional-flow',
        'profesional-efector-servicio.crear-flow',
        'profesional-efector-servicio.baja-flow',
        'profesionales.conteo-efector',
        'profesionales.listado-efector',
        'profesionales.distribucion-servicio-efector',
        'servicio-teleconsulta.configurar-efector-flow',
        'turnos.indicadores-agenda-flow',
        'tratamiento.adherencia-resumen-staff',
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
    }
}
