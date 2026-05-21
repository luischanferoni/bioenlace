<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Seed de desarrollo: care plan activo con actividades para probar la app paciente
 * (`GET /api/v1/clinical/care-plans/active`).
 *
 * Idempotente: no duplica si ya existe un plan con {@see SEED_TITLE} para la persona.
 *
 * Persona objetivo (en orden):
 * 1. Variable de entorno `BIOENLACE_SEED_PERSONA_ID` (id_persona).
 * 2. Primera fila en `personas` con `id_user` no nulo.
 *
 * safeDown elimina el plan, actividades, órdenes y encounter creados por este seed.
 */
class m260521_100009_seed_care_plan_demo extends Migration
{
    private const SEED_TITLE = '[DEV] Care plan demo (app paciente)';

    private const SEED_MARKER = 'seed:m260521_100009_care_plan_demo';

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%care_plan}}', true) === null) {
            echo "    > care_plan no existe; omitir seed.\n";

            return true;
        }

        $personaId = $this->resolveSubjectPersonaId();
        if ($personaId <= 0) {
            echo "    > Sin persona con usuario (id_user); omitir seed. Defina BIOENLACE_SEED_PERSONA_ID si hace falta.\n";

            return true;
        }

        if ($this->findSeedCarePlanId($personaId) !== null) {
            echo "    > Ya existe care plan demo para id_persona={$personaId}; omitir.\n";

            return true;
        }

        $now = date('Y-m-d H:i:s');
        $encounterId = $this->insertEncounter($personaId, $now);
        $carePlanId = $this->insertCarePlan($personaId, $encounterId, $now);
        $medicationRequestId = $this->insertMedicationRequest($personaId, $encounterId, $carePlanId, $now);
        $serviceRequestId = $this->insertServiceRequest($personaId, $encounterId, $carePlanId, $now);
        $this->insertCarePlanActivities($carePlanId, $medicationRequestId, $serviceRequestId, $now);

        echo "    > Seed care plan demo: id_persona={$personaId}, care_plan_id={$carePlanId}, encounter_id={$encounterId}\n";

        return true;
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%care_plan}}', true) === null) {
            return true;
        }

        $planIds = (new Query())
            ->select('id')
            ->from('{{%care_plan}}')
            ->where(['title' => self::SEED_TITLE])
            ->column($this->db);

        if ($planIds === []) {
            echo "    > No hay care plans demo que eliminar.\n";

            return true;
        }

        $encounterIds = (new Query())
            ->select('encounter_id')
            ->from('{{%care_plan}}')
            ->where(['id' => $planIds])
            ->andWhere(['not', ['encounter_id' => null]])
            ->column($this->db);

        $activityRows = (new Query())
            ->from('{{%care_plan_activity}}')
            ->where(['care_plan_id' => $planIds])
            ->all($this->db);

        $medicationIds = [];
        $serviceIds = [];
        foreach ($activityRows as $row) {
            if ($row['kind'] === 'medication-request') {
                $medicationIds[] = (int) $row['resource_id'];
            } elseif ($row['kind'] === 'service-request') {
                $serviceIds[] = (int) $row['resource_id'];
            }
        }

        if ($this->db->schema->getTableSchema('{{%care_plan_activity}}', true) !== null) {
            $this->delete('{{%care_plan_activity}}', ['care_plan_id' => $planIds]);
        }
        if ($medicationIds !== [] && $this->db->schema->getTableSchema('{{%medication_request}}', true) !== null) {
            $this->delete('{{%medication_request}}', ['id' => $medicationIds]);
        }
        if ($serviceIds !== [] && $this->db->schema->getTableSchema('{{%service_request}}', true) !== null) {
            $this->delete('{{%service_request}}', ['id' => $serviceIds]);
        }

        $this->delete('{{%care_plan}}', ['id' => $planIds]);

        if ($encounterIds !== [] && $this->db->schema->getTableSchema('{{%encounter}}', true) !== null) {
            $this->delete('{{%encounter}}', [
                'id' => $encounterIds,
                'note' => self::SEED_MARKER,
            ]);
        }

        echo '    > Eliminado(s) care plan demo: ' . implode(', ', $planIds) . "\n";

        return true;
    }

    private function resolveSubjectPersonaId(): int
    {
        $fromEnv = (int) getenv('BIOENLACE_SEED_PERSONA_ID');
        if ($fromEnv > 0) {
            $exists = (new Query())
                ->from('{{%personas}}')
                ->where(['id_persona' => $fromEnv])
                ->exists($this->db);
            if ($exists) {
                return $fromEnv;
            }
            echo "    > BIOENLACE_SEED_PERSONA_ID={$fromEnv} no existe en personas; se ignora.\n";
        }

        $id = (new Query())
            ->select('id_persona')
            ->from('{{%personas}}')
            ->where(['not', ['id_user' => null]])
            ->andWhere(['>', 'id_user', 0])
            ->orderBy(['id_persona' => SORT_ASC])
            ->scalar($this->db);

        return (int) $id;
    }

    private function findSeedCarePlanId(int $personaId): ?int
    {
        $id = (new Query())
            ->select('id')
            ->from('{{%care_plan}}')
            ->where([
                'subject_persona_id' => $personaId,
                'title' => self::SEED_TITLE,
            ])
            ->andWhere(['deleted_at' => null])
            ->scalar($this->db);

        return $id !== false ? (int) $id : null;
    }

    private function insertEncounter(int $personaId, string $now): int
    {
        $this->insert('{{%encounter}}', [
            'subject_persona_id' => $personaId,
            'encounter_class' => 'AMB',
            'status' => 'finished',
            'period_start' => $now,
            'period_end' => $now,
            'reason_text' => 'Consulta demo para seed de care plan',
            'note' => self::SEED_MARKER,
            'created_at' => $now,
        ]);

        return (int) $this->db->getLastInsertID();
    }

    private function insertCarePlan(int $personaId, int $encounterId, string $now): int
    {
        $this->insert('{{%care_plan}}', [
            'subject_persona_id' => $personaId,
            'status' => 'active',
            'intent' => 'plan',
            'category' => 'chronic',
            'period_start' => $now,
            'encounter_id' => $encounterId,
            'title' => self::SEED_TITLE,
            'description' => self::SEED_MARKER . ' — Tratamiento crónico de ejemplo para listado móvil.',
            'created_at' => $now,
        ]);

        return (int) $this->db->getLastInsertID();
    }

    private function insertMedicationRequest(int $personaId, int $encounterId, int $carePlanId, string $now): int
    {
        $this->insert('{{%medication_request}}', [
            'encounter_id' => $encounterId,
            'subject_persona_id' => $personaId,
            'care_plan_id' => $carePlanId,
            'status' => 'active',
            'intent' => 'order',
            'medication_display' => 'Losartán 50 mg',
            'dosage_text' => '1 comprimido cada 24 h',
            'authored_on' => $now,
            'created_at' => $now,
        ]);

        return (int) $this->db->getLastInsertID();
    }

    private function insertServiceRequest(int $personaId, int $encounterId, int $carePlanId, string $now): int
    {
        $this->insert('{{%service_request}}', [
            'encounter_id' => $encounterId,
            'subject_persona_id' => $personaId,
            'care_plan_id' => $carePlanId,
            'status' => 'active',
            'intent' => 'order',
            'category' => 'laboratory',
            'display' => 'Hemograma completo',
            'created_at' => $now,
        ]);

        return (int) $this->db->getLastInsertID();
    }

    private function insertCarePlanActivities(int $carePlanId, int $medicationRequestId, int $serviceRequestId, string $now): void
    {
        $this->insert('{{%care_plan_activity}}', [
            'care_plan_id' => $carePlanId,
            'kind' => 'medication-request',
            'resource_type' => 'MedicationRequest',
            'resource_id' => $medicationRequestId,
            'sort_order' => 10,
            'status' => 'in-progress',
            'created_at' => $now,
        ]);
        $this->insert('{{%care_plan_activity}}', [
            'care_plan_id' => $carePlanId,
            'kind' => 'service-request',
            'resource_type' => 'ServiceRequest',
            'resource_id' => $serviceRequestId,
            'sort_order' => 20,
            'status' => 'scheduled',
            'created_at' => $now,
        ]);
    }
}
