<?php

namespace common\components\Clinical\Specialty\Odontology;

use common\components\Clinical\Dto\ProcedureDto;
use common\components\Clinical\Enum\CarePlanCategory;
use common\components\Clinical\Enum\ProcedureStatus;
use common\components\Clinical\Service\CarePlanService;
use common\models\Clinical\CarePlan;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
use common\models\Clinical\Procedure;
use common\models\Clinical\ProcedureOdontologyExt;
use common\models\DiagnosticoConsulta;

/**
 * Prácticas y diagnósticos odontológicos sobre {@see Encounter} (ex consultas_odontologia_*).
 */
final class OdontologyEncounterService
{
    private CarePlanService $carePlans;

    public function __construct(?CarePlanService $carePlans = null)
    {
        $this->carePlans = $carePlans ?? new CarePlanService();
    }

    /**
     * @return array{procedures: list<array<string, mixed>>, conditions: list<array<string, mixed>>}
     */
    public function bundleForEncounter(int $encounterId): array
    {
        $procedures = Procedure::find()
            ->alias('p')
            ->innerJoin(['e' => ProcedureOdontologyExt::tableName()], 'e.procedure_id = p.id')
            ->where(['p.encounter_id' => $encounterId, 'p.deleted_at' => null])
            ->orderBy(['p.id' => SORT_ASC])
            ->all();

        $procDtos = [];
        foreach ($procedures as $p) {
            $procDtos[] = ProcedureDto::fromModel($p)->toArray();
        }

        $conditions = Condition::find()
            ->where(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->andWhere(['like', 'note', 'odontology:', false])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $condOut = [];
        foreach ($conditions as $c) {
            $meta = $this->parseOdontologyNote($c->note);
            $condOut[] = [
                'resourceType' => 'Condition',
                'id' => (int) $c->id,
                'code' => $c->code,
                'display' => $c->display,
                'clinicalStatus' => $c->clinical_status,
                'odontology' => $meta,
            ];
        }

        return [
            'procedures' => $procDtos,
            'conditions' => $condOut,
        ];
    }

    /**
     * @param mixed $payload filas IA / JSON (ex ConsultaOdontologiaPracticas)
     */
    public function persistPractices(Encounter $encounter, $payload, ?CarePlan $carePlan = null): CarePlan
    {
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Payload odontología prácticas debe ser un array.');
        }

        $plan = $carePlan ?? $this->resolveOdontologyCarePlan($encounter);

        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }
            $procedure = new Procedure();
            $procedure->encounter_id = $encounter->id;
            $procedure->subject_persona_id = $encounter->subject_persona_id;
            $procedure->status = $this->statusFromTimeQualifier($row['tiempo'] ?? null);
            $procedure->code = (string) ($row['codigo'] ?? $row['conceptId'] ?? '');
            $procedure->display = $row['termino'] ?? $row['Nombre de la practica'] ?? null;
            $procedure->performed_datetime = date('Y-m-d H:i:s');
            if (!$procedure->save()) {
                throw new \RuntimeException('Procedure odonto: ' . json_encode($procedure->getErrors()));
            }

            $ext = new ProcedureOdontologyExt();
            $ext->procedure_id = $procedure->id;
            $ext->tooth_number = isset($row['pieza']) ? (string) $row['pieza'] : null;
            $ext->surfaces = isset($row['caras']) ? (string) $row['caras'] : null;
            $ext->time_qualifier = $this->normalizeTimeQualifier($row['tiempo'] ?? null);
            if (!$ext->save()) {
                throw new \RuntimeException('Procedure odonto ext: ' . json_encode($ext->getErrors()));
            }

            $this->carePlans->addProcedureActivity($plan, $procedure);
        }

        return $plan;
    }

    /**
     * @param mixed $payload filas ex ConsultaOdontologiaDiagnosticos
     */
    public function persistDiagnostics(Encounter $encounter, $payload): void
    {
        if (!is_array($payload)) {
            return;
        }
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }
            $condition = new Condition();
            $condition->encounter_id = $encounter->id;
            $condition->subject_persona_id = $encounter->subject_persona_id;
            $condition->code = (string) ($row['codigo'] ?? $row['conceptId'] ?? '');
            $condition->display = $row['termino'] ?? null;
            $condition->clinical_status = DiagnosticoConsulta::CLINICAL_STATUS_ACTIVE;
            $condition->verification_status = DiagnosticoConsulta::VERIFICATION_STATUS_CONFIRMED;
            $condition->recorded_date = date('Y-m-d H:i:s');
            $condition->note = $this->buildOdontologyNote($row);
            if ($condition->code === '') {
                continue;
            }
            $condition->save(false);
        }
    }

    private function resolveOdontologyCarePlan(Encounter $encounter): CarePlan
    {
        $existing = CarePlan::find()
            ->where([
                'encounter_id' => $encounter->id,
                'category' => CarePlanCategory::ODONTOLOGY,
                'deleted_at' => null,
            ])
            ->andWhere(['not in', 'status', ['completed', 'revoked', 'entered-in-error']])
            ->one();

        if ($existing instanceof CarePlan) {
            return $existing;
        }

        $plan = $this->carePlans->createDraft(
            (int) $encounter->subject_persona_id,
            CarePlanCategory::ODONTOLOGY,
            (int) $encounter->id
        );

        return $this->carePlans->activate($plan);
    }

    private function statusFromTimeQualifier(?string $tiempo): string
    {
        $t = $this->normalizeTimeQualifier($tiempo);

        return $t === 'FUTURA' ? ProcedureStatus::PREPARATION : ProcedureStatus::COMPLETED;
    }

    private function normalizeTimeQualifier(?string $tiempo): ?string
    {
        if ($tiempo === null || $tiempo === '') {
            return 'PRESENTE';
        }
        $u = strtoupper(trim($tiempo));
        if (in_array($u, ['PASADA', 'PRESENTE', 'FUTURA'], true)) {
            return $u;
        }

        return 'PRESENTE';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildOdontologyNote(array $row): string
    {
        $meta = [
            'pieza' => $row['pieza'] ?? null,
            'caras' => $row['caras'] ?? null,
            'tipo' => $row['tipo'] ?? null,
        ];

        return 'odontology:' . json_encode($meta, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseOdontologyNote(?string $note): array
    {
        if ($note === null || strpos($note, 'odontology:') !== 0) {
            return [];
        }
        $json = substr($note, strlen('odontology:'));
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
