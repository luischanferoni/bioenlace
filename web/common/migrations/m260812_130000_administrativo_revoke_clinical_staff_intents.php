<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Administrativo (admisión): quitar intents de operación clínica / RRHH / métricas
 * que no corresponden al rol y ensuciaban atajos RBAC-driven.
 *
 * Conserva urgencias + internación operativa + representación (si estaban).
 */
class m260812_130000_administrativo_revoke_clinical_staff_intents extends Migration
{
    private const ROLE = 'Administrativo';

    /** @var list<string> */
    private const REVOKE_INTENT_IDS = [
        'condicion-laboral.editar-propio',
        'condicion-laboral.editar-staff',
        'profesional-agenda.configurar-propio',
        'profesional-agenda.configurar-staff',
        'profesional-cobertura.gestionar-propio',
        'profesional-cobertura.gestionar-staff',
        'profesional-horarios.gestionar-propio',
        'profesional-identidad.editar-staff',
        'profesional-efector-servicio.crear-flow',
        'profesional-efector-servicio.baja-flow',
        'profesionales.conteo-efector',
        'profesionales.listado-efector',
        'profesionales.distribucion-servicio-efector',
        'licencia.cargar-como-profesional-flow',
        'licencia.cargar-para-profesional-flow',
        'servicio-teleconsulta.configurar-efector-flow',
        'turnos.indicadores-agenda-flow',
        'tratamiento.adherencia-resumen-staff',
        'care-packs.asistencia-pre-consulta-flow',
        'internacion.epicrisis-plantilla-admin',
        'data-access.editar',
        'data-access.info',
        'data-access.listar',
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
