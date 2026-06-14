<?php

namespace common\components\Domain\Clinical\Laboratory\Service;

use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\Observation;
use common\models\Person\Persona;
use Yii;

/**
 * Informe de laboratorio ficticio para desarrollo / pruebas de UI y asistente.
 */
final class LaboratoryDemoSeedService
{
    public const SOURCE_SYSTEM = 'demo';

    public const EXTERNAL_ID_PREFIX = 'seed-lab-demo-';

    /**
     * Crea o actualiza un informe demo con analitos para la persona indicada.
     *
     * @return array{report_id: int, created: bool, observations: int}
     */
    public function upsertForPersona(int $idPersona): array
    {
        if (Persona::findOne($idPersona) === null) {
            throw new \InvalidArgumentException("No existe personas.id_persona={$idPersona}");
        }

        if (Yii::$app->db->schema->getTableSchema('{{%diagnostic_report}}', true) === null) {
            throw new \RuntimeException('Tabla diagnostic_report inexistente. Ejecutá m260523_100001_laboratory_diagnostic_report.');
        }

        $externalId = self::EXTERNAL_ID_PREFIX . $idPersona;
        $issuedAt = date('Y-m-d H:i:s');

        $report = DiagnosticReport::findOne([
            'source_system' => self::SOURCE_SYSTEM,
            'external_id' => $externalId,
        ]) ?? new DiagnosticReport();

        $created = $report->isNewRecord;

        $report->subject_persona_id = $idPersona;
        $report->encounter_id = null;
        $report->source_system = self::SOURCE_SYSTEM;
        $report->external_id = $externalId;
        $report->status = 'final';
        $report->code = '24323-8';
        $report->code_system = 'http://loinc.org';
        $report->display = '[DEV] Hemograma y bioquímica (demo)';
        $report->issued_at = $issuedAt;
        $report->conclusion = 'Valores dentro de límites de referencia (datos de prueba Bioenlace).';
        $report->payload_json = json_encode([
            'resourceType' => 'DiagnosticReport',
            'id' => $externalId,
            'status' => 'final',
            'note' => 'Generado por LaboratoryDemoSeedService',
        ], JSON_UNESCAPED_UNICODE);

        if (!$report->save()) {
            throw new \RuntimeException('DiagnosticReport: ' . json_encode($report->getErrors()));
        }

        $reportId = (int) $report->id;
        $obsCount = $this->upsertObservations($idPersona, $reportId);

        return [
            'report_id' => $reportId,
            'created' => $created,
            'observations' => $obsCount,
        ];
    }

    /**
     * Elimina el informe demo de la persona (soft-delete si aplica).
     */
    public function removeForPersona(int $idPersona): bool
    {
        $externalId = self::EXTERNAL_ID_PREFIX . $idPersona;
        $report = DiagnosticReport::findOne([
            'source_system' => self::SOURCE_SYSTEM,
            'external_id' => $externalId,
            'deleted_at' => null,
        ]);
        if ($report === null) {
            return false;
        }

        foreach ($report->observations as $obs) {
            $obs->delete();
        }

        return (bool) $report->delete();
    }

    private function upsertObservations(int $idPersona, int $reportId): int
    {
        $analytes = [
            ['code' => '2345-7', 'name' => 'Glucosa', 'value' => 95.0, 'unit' => 'mg/dL'],
            ['code' => '2160-0', 'name' => 'Creatinina', 'value' => 0.92, 'unit' => 'mg/dL'],
            ['code' => '718-7', 'name' => 'Hemoglobina', 'value' => 14.5, 'unit' => 'g/dL'],
            ['code' => '2093-3', 'name' => 'Colesterol total', 'value' => 185.0, 'unit' => 'mg/dL'],
        ];

        $count = 0;
        foreach ($analytes as $row) {
            $externalId = self::SOURCE_SYSTEM . '-dr-' . $reportId . '-obs-' . $row['code'];
            $obs = Observation::findOne([
                'source_system' => self::SOURCE_SYSTEM,
                'external_id' => $externalId,
            ]) ?? new Observation();

            $obs->subject_persona_id = $idPersona;
            $obs->encounter_id = null;
            $obs->diagnostic_report_id = $reportId;
            $obs->source_system = self::SOURCE_SYSTEM;
            $obs->external_id = $externalId;
            $obs->status = 'final';
            $obs->category = Observation::CATEGORY_EXAM;
            $obs->code = $row['code'];
            $obs->code_system = 'http://loinc.org';
            $obs->value_string = $row['name'];
            $obs->value_quantity = $row['value'];
            $obs->value_unit = $row['unit'];
            $obs->effective_datetime = date('Y-m-d H:i:s');

            if (!$obs->save()) {
                throw new \RuntimeException('Observation: ' . json_encode($obs->getErrors()));
            }
            $count++;
        }

        return $count;
    }
}
