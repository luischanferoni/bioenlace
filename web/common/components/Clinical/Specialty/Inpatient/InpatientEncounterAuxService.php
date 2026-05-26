<?php

namespace common\components\Clinical\Specialty\Inpatient;

use common\models\Clinical\Encounter;
use common\models\Clinical\MedicationAdministration;
use common\models\Clinical\NutritionOrder;
use common\models\Clinical\Observation;
use common\models\ConsultaBalanceHidrico;
use common\models\ConsultaRegimen;
use common\models\SegNivelInternacion;

/**
 * Balance hídrico, régimen y suministro de medicación en internación (FHIR).
 */
final class InpatientEncounterAuxService
{
    public const OBS_CATEGORY_FLUID_BALANCE = 'fluid-balance';
    public const OBS_CODE_FLUID_BALANCE = 'fluid-balance-entry';

    /**
     * @return ConsultaBalanceHidrico[]
     */
    public function listFluidBalancesForInternacion(SegNivelInternacion $internacion): array
    {
        $rows = Observation::find()
            ->alias('o')
            ->innerJoin(['enc' => Encounter::tableName()], 'enc.id = o.encounter_id')
            ->where([
                'o.category' => self::OBS_CATEGORY_FLUID_BALANCE,
                'enc.parent_type' => Encounter::PARENT_INTERNACION,
                'enc.parent_id' => (int) $internacion->id,
            ])
            ->andWhere(['o.deleted_at' => null, 'enc.deleted_at' => null])
            ->orderBy(['o.effective_datetime' => SORT_ASC, 'o.id' => SORT_ASC])
            ->all();

        $out = [];
        foreach ($rows as $obs) {
            if (!$obs instanceof Observation) {
                continue;
            }
            $mapped = $this->mapObservationToBalanceHidrico($obs);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * @return ConsultaRegimen[]
     */
    public function listRegimensForInternacion(SegNivelInternacion $internacion): array
    {
        $rows = NutritionOrder::find()
            ->alias('n')
            ->addSelect([
                'n.*',
                'consulta_fecha' => 'DATE_FORMAT(enc.created_at, "%d/%m/%Y %H:%i")',
            ])
            ->innerJoin(['enc' => Encounter::tableName()], 'enc.id = n.encounter_id')
            ->where([
                'enc.parent_type' => Encounter::PARENT_INTERNACION,
                'enc.parent_id' => (int) $internacion->id,
            ])
            ->andWhere(['n.deleted_at' => null, 'enc.deleted_at' => null])
            ->orderBy(['n.id' => SORT_ASC])
            ->asArray()
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->mapNutritionOrderRowToRegimen($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function persistFluidBalanceRow(Encounter $encounter, array $row): Observation
    {
        $tipo = (string) ($row['tipo_registro'] ?? '');
        $codIngreso = isset($row['cod_ingreso']) ? (int) $row['cod_ingreso'] : null;
        $codEgreso = isset($row['cod_egreso']) ? (int) $row['cod_egreso'] : null;
        $cantidad = isset($row['cantidad']) ? (int) $row['cantidad'] : null;

        $meta = [
            'tipo_registro' => $tipo,
            'cod_ingreso' => $codIngreso,
            'cod_egreso' => $codEgreso,
            'hora_inicio' => $row['hora_inicio'] ?? null,
            'hora_fin' => $row['hora_fin'] ?? null,
            'fecha' => $this->normalizeDateToYmd($row['fecha'] ?? null),
        ];

        $obs = new Observation();
        $obs->encounter_id = (int) $encounter->id;
        $obs->subject_persona_id = (int) $encounter->subject_persona_id;
        $obs->status = 'final';
        $obs->category = self::OBS_CATEGORY_FLUID_BALANCE;
        $obs->code = self::OBS_CODE_FLUID_BALANCE;
        $obs->value_quantity = $cantidad;
        $obs->value_unit = 'mL';
        $obs->value_json = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $obs->effective_datetime = $this->buildEffectiveDatetime(
            $meta['fecha'],
            $meta['hora_inicio'] ?? null
        );

        if (!$obs->save()) {
            throw new \RuntimeException('Observation balance: ' . json_encode($obs->getErrors()));
        }

        return $obs;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function persistRegimenRow(Encounter $encounter, array $row): NutritionOrder
    {
        $conceptId = trim((string) ($row['concept_id'] ?? $row['conceptId'] ?? ''));
        $indicaciones = trim((string) ($row['indicaciones'] ?? ''));

        $order = new NutritionOrder();
        $order->encounter_id = (int) $encounter->id;
        $order->subject_persona_id = (int) $encounter->subject_persona_id;
        $order->status = NutritionOrder::STATUS_ACTIVE;
        $order->oral_diet_json = json_encode([
            'concept_id' => $conceptId !== '' ? $conceptId : null,
        ], JSON_UNESCAPED_UNICODE);
        $order->note = $indicaciones !== '' ? $indicaciones : null;

        if (!$order->save()) {
            throw new \RuntimeException('NutritionOrder: ' . json_encode($order->getErrors()));
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function persistMedicationSupplyRow(Encounter $encounter, array $row): MedicationAdministration
    {
        $fecha = $this->normalizeDateToYmd($row['fecha'] ?? null);
        $hora = isset($row['hora']) ? (string) $row['hora'] : null;
        $meta = [
            'fecha' => $fecha,
            'hora' => $hora,
            'observacion' => $row['observacion'] ?? $row['observaciones'] ?? null,
            'id_internacion_medicamento' => isset($row['id_internacion_medicamento'])
                ? (int) $row['id_internacion_medicamento']
                : null,
        ];

        $admin = new MedicationAdministration();
        $admin->encounter_id = (int) $encounter->id;
        $admin->status = MedicationAdministration::STATUS_COMPLETED;
        $admin->effective_datetime = $this->buildEffectiveDatetime($fecha, $hora);
        if (isset($row['id_internacion_medicamento']) && (int) $row['id_internacion_medicamento'] > 0) {
            $admin->medication_request_id = (int) $row['id_internacion_medicamento'];
        }
        $admin->dosage_json = json_encode($meta, JSON_UNESCAPED_UNICODE);

        if (!$admin->save()) {
            throw new \RuntimeException('MedicationAdministration: ' . json_encode($admin->getErrors()));
        }

        return $admin;
    }

    private function mapObservationToBalanceHidrico(Observation $obs): ?ConsultaBalanceHidrico
    {
        $meta = $this->decodeJson($obs->value_json);
        if ($meta === []) {
            return null;
        }

        $model = new ConsultaBalanceHidrico();
        $model->setIsNewRecord(false);
        $model->id = (int) $obs->id;
        $model->id_consulta = (int) $obs->encounter_id;
        $model->tipo_registro = (string) ($meta['tipo_registro'] ?? '');
        $model->cod_ingreso = isset($meta['cod_ingreso']) ? (int) $meta['cod_ingreso'] : null;
        $model->cod_egreso = isset($meta['cod_egreso']) ? (int) $meta['cod_egreso'] : null;
        $model->cantidad = $obs->value_quantity !== null ? (int) $obs->value_quantity : null;
        $model->hora_inicio = $meta['hora_inicio'] ?? null;
        $model->hora_fin = $meta['hora_fin'] ?? null;
        $fecha = $meta['fecha'] ?? null;
        if (is_string($fecha) && $fecha !== '') {
            $model->fecha = date('d/m/Y', strtotime($fecha));
        }

        return $model;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapNutritionOrderRowToRegimen(array $row): ConsultaRegimen
    {
        $diet = $this->decodeJson($row['oral_diet_json'] ?? null);

        $model = new ConsultaRegimen();
        $model->setIsNewRecord(false);
        $model->id = (int) ($row['id'] ?? 0);
        $model->id_consulta = (int) ($row['encounter_id'] ?? 0);
        $model->concept_id = isset($diet['concept_id']) ? (string) $diet['concept_id'] : null;
        $model->indicaciones = (string) ($row['note'] ?? '');
        if (isset($row['consulta_fecha'])) {
            $model->setQueryExtraValue('consulta_fecha', $row['consulta_fecha']);
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeDateToYmd(?string $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
            $dt = \DateTime::createFromFormat('d/m/Y', $fecha);

            return $dt ? $dt->format('Y-m-d') : null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $fecha;
        }

        return null;
    }

    private function buildEffectiveDatetime(?string $fechaYmd, ?string $hora): ?string
    {
        if ($fechaYmd === null || $fechaYmd === '') {
            return null;
        }
        $time = ($hora !== null && $hora !== '') ? $hora : '00:00:00';
        if (strlen($time) === 5) {
            $time .= ':00';
        }

        return $fechaYmd . ' ' . $time;
    }
}
