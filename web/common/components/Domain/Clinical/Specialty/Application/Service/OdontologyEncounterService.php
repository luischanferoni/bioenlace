<?php

namespace common\components\Domain\Clinical\Specialty\Application\Service;

use common\components\Domain\Clinical\Capture\Domain\RowContract\OdontologiaItemRowContract;
use common\components\Domain\Clinical\Encounter\Domain\ConditionVerificationStatus;

use common\components\Domain\Clinical\Encounter\Domain\ConditionClinicalStatus;

use common\components\Domain\Clinical\Encounter\Domain\Model\ProcedureDto;
use common\components\Domain\Clinical\CarePlan\Domain\CarePlanCategory;
use common\components\Domain\Clinical\Encounter\Domain\ProcedureStatus;
use common\components\Domain\Clinical\CarePlan\Application\Service\CarePlanService;
use common\models\Clinical\CarePlan;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
use common\models\Clinical\Procedure;
use common\models\Clinical\ProcedureOdontologyExt;

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
            ->andWhere(['not like', 'note', 'odontology_state:', false])
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
     * @param mixed $payload filas IA / JSON de prácticas dentales
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
            $procedure->status = $this->statusFromTimeQualifier(OdontologiaItemRowContract::extractTiempo($row));
            $procedure->code = OdontologiaItemRowContract::extractCodigo($row);
            $procedure->display = OdontologiaItemRowContract::extractTermino($row);
            $procedure->performed_datetime = date('Y-m-d H:i:s');
            if (!$procedure->save()) {
                throw new \RuntimeException('Procedure odonto: ' . json_encode($procedure->getErrors()));
            }

            $ext = new ProcedureOdontologyExt();
            $ext->procedure_id = $procedure->id;
            $ext->tooth_number = OdontologiaItemRowContract::extractPieza($row) ?: null;
            $ext->surfaces = OdontologiaItemRowContract::extractCaras($row) ?: null;
            $ext->time_qualifier = $this->normalizeTimeQualifier(OdontologiaItemRowContract::extractTiempo($row));
            if (!$ext->save()) {
                throw new \RuntimeException('Procedure odonto ext: ' . json_encode($ext->getErrors()));
            }

            $this->carePlans->addProcedureActivity($plan, $procedure);
        }

        return $plan;
    }

    /**
     * @return list<array{pieza: int|string|null, codigo: string|null, caras?: string|null, tipo?: string|null}>
     */
    public function getCpoStatesForPersonUntilEncounter(int $personaId, int $encounterId): array
    {
        $rows = Condition::find()
            ->alias('c')
            ->select(['c.note'])
            ->innerJoin(['enc' => Encounter::tableName()], 'enc.id = c.encounter_id')
            ->where([
                'enc.subject_persona_id' => $personaId,
                'c.clinical_status' => ConditionClinicalStatus::ACTIVE,
            ])
            ->andWhere(['<=', 'enc.id', $encounterId])
            ->andWhere(['enc.deleted_at' => null, 'c.deleted_at' => null])
            ->andWhere(['like', 'c.note', 'odontology_state:', false])
            ->asArray()
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $meta = $this->parseOdontologyStateNote($row['note'] ?? null);
            if ($meta === []) {
                continue;
            }
            $out[] = [
                'pieza' => $meta['pieza'] ?? null,
                'codigo' => isset($meta['codigo']) ? (string) $meta['codigo'] : null,
                'caras' => $meta['caras'] ?? null,
                'tipo' => $meta['tipo'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{pieza: int|string|null, codigo: string|null, caras?: string|null, tipo?: string|null}>
     */
    public function getCpoStatesForPerson(int $personaId): array
    {
        $maxEncounterId = (int) Encounter::find()
            ->where(['subject_persona_id' => $personaId, 'deleted_at' => null])
            ->max('id');

        if ($maxEncounterId <= 0) {
            return [];
        }

        return $this->getCpoStatesForPersonUntilEncounter($personaId, $maxEncounterId);
    }

    /**
     * @param mixed $payload filas de estado dental
     */
    public function persistToothStates(Encounter $encounter, $payload): void
    {
        if (!is_array($payload)) {
            return;
        }
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }
            $codigo = OdontologiaItemRowContract::extractCodigo($row);
            if ($codigo === '') {
                continue;
            }
            $condition = new Condition();
            $condition->encounter_id = $encounter->id;
            $condition->subject_persona_id = $encounter->subject_persona_id;
            $condition->code = is_numeric($codigo) ? $codigo : null;
            $condition->display = OdontologiaItemRowContract::extractTermino($row);
            $condition->clinical_status = ConditionClinicalStatus::ACTIVE;
            $condition->verification_status = ConditionVerificationStatus::CONFIRMED;
            $condition->recorded_date = date('Y-m-d H:i:s');
            $condition->note = $this->buildOdontologyStateNote($row);
            $condition->save(false);
        }
    }

    /**
     * @param mixed $payload filas de diagnósticos dentales
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
            $codigo = OdontologiaItemRowContract::extractCodigo($row);
            if ($codigo === '') {
                continue;
            }
            $condition = new Condition();
            $condition->encounter_id = $encounter->id;
            $condition->subject_persona_id = $encounter->subject_persona_id;
            $condition->code = $codigo;
            $condition->display = OdontologiaItemRowContract::extractTermino($row);
            $condition->clinical_status = ConditionClinicalStatus::ACTIVE;
            $condition->verification_status = ConditionVerificationStatus::CONFIRMED;
            $condition->recorded_date = date('Y-m-d H:i:s');
            $condition->note = $this->buildOdontologyNote($row);
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

    private function normalizeTimeQualifier(?string $tiempo): string
    {
        if ($tiempo === null || $tiempo === '') {
            return OdontologiaItemRowContract::DEFAULT_TIME_QUALIFIER;
        }
        $u = strtoupper(trim($tiempo));
        if (in_array($u, OdontologiaItemRowContract::TIME_QUALIFIERS, true)) {
            return $u;
        }

        return OdontologiaItemRowContract::DEFAULT_TIME_QUALIFIER;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildOdontologyNote(array $row): string
    {
        $meta = [
            'pieza' => OdontologiaItemRowContract::extractPieza($row) ?: null,
            'caras' => OdontologiaItemRowContract::extractCaras($row) ?: null,
            'tipo' => OdontologiaItemRowContract::extractTipo($row) ?: null,
        ];

        return 'odontology:' . json_encode($meta, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildOdontologyStateNote(array $row): string
    {
        $meta = [
            'pieza' => OdontologiaItemRowContract::extractPieza($row) ?: null,
            'caras' => OdontologiaItemRowContract::extractCaras($row) ?: null,
            'tipo' => OdontologiaItemRowContract::extractTipo($row) ?: null,
            'codigo' => OdontologiaItemRowContract::extractCodigo($row) ?: null,
        ];

        return 'odontology_state:' . json_encode($meta, JSON_UNESCAPED_UNICODE);
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

    /**
     * @return array<string, mixed>
     */
    private function parseOdontologyStateNote(?string $note): array
    {
        if ($note === null || strpos($note, 'odontology_state:') !== 0) {
            return [];
        }
        $json = substr($note, strlen('odontology_state:'));
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
