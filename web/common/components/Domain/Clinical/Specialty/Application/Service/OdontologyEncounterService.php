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
use common\models\Clinical\Observation;
use common\models\Clinical\ObservationOdontologyExt;
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
            'toothStates' => $this->toothStatesForEncounter($encounterId),
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
     * Estado acumulado de las piezas hasta el encounter indicado.
     *
     * @return list<array{pieza: string|null, codigo: string|null, caras: string|null, tipo: string|null, tiempo: string|null, display: string|null, encounterId: int|null, registradoEn: string|null}>
     */
    public function getCpoStatesForPersonUntilEncounter(int $personaId, int $encounterId): array
    {
        return $this->mapStateRows(
            $this->stateQuery()
                ->andWhere(['enc.subject_persona_id' => $personaId])
                ->andWhere(['<=', 'enc.id', $encounterId])
                ->asArray()
                ->all()
        );
    }

    /**
     * Estado dental registrado en un encounter.
     *
     * @return list<array{pieza: string|null, codigo: string|null, caras: string|null, tipo: string|null, tiempo: string|null, display: string|null, encounterId: int|null, registradoEn: string|null}>
     */
    public function toothStatesForEncounter(int $encounterId): array
    {
        return $this->mapStateRows(
            $this->stateQuery()
                ->andWhere(['o.encounter_id' => $encounterId])
                ->asArray()
                ->all()
        );
    }

    /**
     * Agrupa los estados por pieza para dibujar el odontograma.
     *
     * Las piezas sin registro no se inventan: el cliente dibuja el diente sano.
     *
     * @param list<array<string, mixed>> $states
     * @return array{encounterId: int, teeth: list<array<string, mixed>>, totals: array{teeth: int, states: int, unassigned: int}}
     */
    public static function buildOdontogram(array $states, int $encounterId): array
    {
        $byTooth = [];
        $unassigned = 0;
        $stateCount = 0;

        foreach ($states as $state) {
            $stateCount++;
            $tooth = isset($state['pieza']) ? trim((string) $state['pieza']) : '';
            if ($tooth === '') {
                $unassigned++;
                continue;
            }
            $byTooth[$tooth][] = $state;
        }

        uksort($byTooth, static function ($left, $right): int {
            if (ctype_digit((string) $left) && ctype_digit((string) $right)) {
                return (int) $left <=> (int) $right;
            }

            return strcmp((string) $left, (string) $right);
        });

        $teeth = [];
        foreach ($byTooth as $tooth => $history) {
            $teeth[] = [
                'tooth' => (string) $tooth,
                'dentition' => OdontologiaItemRowContract::dentitionForTooth((string) $tooth),
                'current' => $history[count($history) - 1],
                'history' => array_values($history),
            ];
        }

        return [
            'encounterId' => $encounterId,
            'teeth' => $teeth,
            'totals' => [
                'teeth' => count($teeth),
                'states' => $stateCount,
                'unassigned' => $unassigned,
            ],
        ];
    }

    /**
     * Odontograma del paciente al cierre del encounter indicado.
     *
     * @return array{encounterId: int, teeth: list<array<string, mixed>>, totals: array{teeth: int, states: int, unassigned: int}}
     */
    public function odontogramForEncounter(Encounter $encounter): array
    {
        return self::buildOdontogram(
            $this->getCpoStatesForPersonUntilEncounter(
                (int) $encounter->subject_persona_id,
                (int) $encounter->id
            ),
            (int) $encounter->id
        );
    }

    /**
     * Odontograma del paciente a la fecha de su último encounter registrado.
     *
     * @return array{encounterId: int, teeth: list<array<string, mixed>>, totals: array{teeth: int, states: int, unassigned: int}}
     */
    public function odontogramForPerson(int $personaId): array
    {
        $lastEncounterId = (int) Encounter::find()
            ->where(['subject_persona_id' => $personaId, 'deleted_at' => null])
            ->max('id');

        return self::buildOdontogram(
            $lastEncounterId > 0
                ? $this->getCpoStatesForPersonUntilEncounter($personaId, $lastEncounterId)
                : [],
            max(0, $lastEncounterId)
        );
    }

    /**
     * @return list<array{pieza: string|null, codigo: string|null, caras: string|null, tipo: string|null, tiempo: string|null, display: string|null, encounterId: int|null, registradoEn: string|null}>
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
     * Campos normalizados de un estado dental extraído.
     *
     * @param array<string, mixed> $row
     * @return array{pieza: string|null, codigo: string, caras: string|null, tipo: string|null, termino: string|null, tiempo: string|null}|null
     */
    public static function toothStateFields(array $row): ?array
    {
        $codigo = OdontologiaItemRowContract::extractCodigo($row);
        if ($codigo === '') {
            return null;
        }

        return [
            'pieza' => OdontologiaItemRowContract::extractPieza($row) ?: null,
            'codigo' => $codigo,
            'caras' => OdontologiaItemRowContract::extractCaras($row) ?: null,
            'tipo' => OdontologiaItemRowContract::extractTipo($row) ?: null,
            'termino' => OdontologiaItemRowContract::extractTermino($row),
            'tiempo' => OdontologiaItemRowContract::extractTiempo($row),
        ];
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
            $fields = self::toothStateFields($row);
            if ($fields === null) {
                continue;
            }

            $observation = new Observation();
            $observation->encounter_id = $encounter->id;
            $observation->subject_persona_id = $encounter->subject_persona_id;
            $observation->status = 'final';
            $observation->category = Observation::CATEGORY_ODONTOLOGY;
            $observation->code = $fields['codigo'];
            $observation->code_system = 'http://snomed.info/sct';
            $observation->value_string = $fields['termino'];
            $observation->value_json = json_encode([
                'pieza' => $fields['pieza'],
                'caras' => $fields['caras'],
                'tipo' => $fields['tipo'],
            ], JSON_UNESCAPED_UNICODE);
            $observation->effective_datetime = date('Y-m-d H:i:s');
            if (!$observation->save()) {
                throw new \RuntimeException(
                    'Observation odonto estado: ' . json_encode($observation->getErrors())
                );
            }

            $ext = new ObservationOdontologyExt();
            $ext->observation_id = $observation->id;
            $ext->tooth_number = $fields['pieza'];
            $ext->surfaces = $fields['caras'];
            $ext->time_qualifier = $fields['tiempo'];
            if (!$ext->save()) {
                throw new \RuntimeException('Observation odonto ext: ' . json_encode($ext->getErrors()));
            }
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
     * Consulta base de estados dentales con su extensión de pieza.
     *
     * @return \yii\db\ActiveQuery
     */
    private function stateQuery()
    {
        return Observation::find()
            ->alias('o')
            ->innerJoin(['e' => ObservationOdontologyExt::tableName()], 'e.observation_id = o.id')
            ->innerJoin(['enc' => Encounter::tableName()], 'enc.id = o.encounter_id')
            ->select([
                'o.id',
                'o.code',
                'o.value_string',
                'o.value_json',
                'o.encounter_id',
                'o.effective_datetime',
                'e.tooth_number',
                'e.surfaces',
                'e.time_qualifier',
            ])
            ->where([
                'o.category' => Observation::CATEGORY_ODONTOLOGY,
                'o.deleted_at' => null,
                'enc.deleted_at' => null,
            ])
            ->andWhere(['not', ['o.status' => 'entered-in-error']])
            ->orderBy(['o.id' => SORT_ASC]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{pieza: string|null, codigo: string|null, caras: string|null, tipo: string|null, tiempo: string|null, display: string|null, encounterId: int|null, registradoEn: string|null}>
     */
    private function mapStateRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $meta = $this->decodeValueJson($row['value_json'] ?? null);
            $out[] = [
                'pieza' => isset($row['tooth_number']) && $row['tooth_number'] !== ''
                    ? (string) $row['tooth_number']
                    : null,
                'codigo' => isset($row['code']) && $row['code'] !== '' ? (string) $row['code'] : null,
                'caras' => isset($row['surfaces']) && $row['surfaces'] !== ''
                    ? (string) $row['surfaces']
                    : null,
                'tipo' => isset($meta['tipo']) ? (string) $meta['tipo'] : null,
                'tiempo' => isset($row['time_qualifier']) && $row['time_qualifier'] !== ''
                    ? (string) $row['time_qualifier']
                    : null,
                'display' => isset($row['value_string']) && $row['value_string'] !== ''
                    ? (string) $row['value_string']
                    : null,
                'encounterId' => isset($row['encounter_id']) ? (int) $row['encounter_id'] : null,
                'registradoEn' => isset($row['effective_datetime']) ? (string) $row['effective_datetime'] : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeValueJson($valueJson): array
    {
        if (!is_string($valueJson) || $valueJson === '') {
            return [];
        }
        $decoded = json_decode($valueJson, true);

        return is_array($decoded) ? $decoded : [];
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
