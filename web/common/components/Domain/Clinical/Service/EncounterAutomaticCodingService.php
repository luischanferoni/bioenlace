<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\AiContext\PatientAiContextBuilder;
use common\components\Ai\IAManager;
use common\components\Platform\Core\Product\ClinicalTextIaMetadata;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterDefinition;
use common\models\DiagnosticoConsulta;
use Yii;

/**
 * Codificación automática CIE-10 / SNOMED desde texto clínico (IA decide y persiste; sin UI de sugerencias).
 */
final class EncounterAutomaticCodingService
{
    public const IA_CONTEXT = 'encounter-codificacion-automatica';

    public const CODE_SYSTEM_ICD10 = 'http://hl7.org/fhir/sid/icd-10';

    public const CODE_SYSTEM_SNOMED = 'http://snomed.info/sct';

    /**
     * @param array<string, mixed> $datosExtraidos
     */
    public static function codeAndPersistForEncounter(
        Encounter $encounter,
        array $datosExtraidos = [],
        ?EncounterDefinition $configuracion = null
    ): int {
        if (!(Yii::$app->params['encounter_auto_codificacion_habilitada'] ?? true)) {
            return 0;
        }

        $clinicalText = self::buildClinicalText($encounter, $datosExtraidos, $configuracion);
        if (trim($clinicalText) === '') {
            return 0;
        }

        $diagnostics = self::inferCodesWithIa(
            $clinicalText,
            self::buildDiagnosisHints($datosExtraidos, $configuracion),
            (int) $encounter->subject_persona_id
        );
        if ($diagnostics === []) {
            return 0;
        }

        return self::persistDiagnostics($encounter, $diagnostics);
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     */
    private static function buildClinicalText(
        Encounter $encounter,
        array $datosExtraidos,
        ?EncounterDefinition $configuracion = null
    ): string {
        $dxTerms = self::extractDiagnosisTerms($datosExtraidos, $configuracion);
        if ($dxTerms === []) {
            return '';
        }

        $parts = [];
        $parts[] = "Diagnósticos a codificar:\n- " . implode("\n- ", $dxTerms);

        // Contexto libre solo como apoyo; el prompt prohíbe codificar síntomas/motivos extras.
        $contexto = [];
        if (trim((string) ($encounter->note ?? '')) !== '') {
            $contexto[] = trim((string) $encounter->note);
        }
        if (trim((string) ($encounter->reason_text ?? '')) !== '') {
            $contexto[] = 'Motivo: ' . trim((string) $encounter->reason_text);
        }
        if ($contexto !== []) {
            $parts[] = "Contexto clínico (no codificar motivos/síntomas como diagnóstico):\n"
                . implode("\n\n", $contexto);
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     */
    private static function buildDiagnosisHints(
        array $datosExtraidos,
        ?EncounterDefinition $configuracion = null
    ): string {
        $lines = [];
        foreach (self::extractDiagnosisRows($datosExtraidos, $configuracion) as $row) {
            $term = self::rowTerm($row);
            if ($term === '') {
                continue;
            }
            $hasCode = trim((string) ($row['codigo'] ?? $row['codigo_cie10'] ?? $row['cie10'] ?? '')) !== ''
                || trim((string) ($row['codigo_snomed'] ?? '')) !== '';
            if ($hasCode) {
                continue;
            }
            $lines[] = '- ' . $term;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     * @return list<string>
     */
    private static function extractDiagnosisTerms(
        array $datosExtraidos,
        ?EncounterDefinition $configuracion = null
    ): array {
        $terms = [];
        foreach (self::extractDiagnosisRows($datosExtraidos, $configuracion) as $row) {
            $term = self::rowTerm($row);
            if ($term !== '') {
                $terms[] = $term;
            }
        }

        return array_values(array_unique($terms));
    }

    /**
     * @param array<string, mixed>|string $row
     */
    private static function rowTerm($row): string
    {
        if (is_string($row)) {
            return trim($row);
        }
        if (!is_array($row)) {
            return '';
        }

        foreach (['termino', 'descripcion', 'texto', 'label', 'display', 'diagnostico'] as $key) {
            $term = trim((string) ($row[$key] ?? ''));
            if ($term !== '') {
                return $term;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     * @return list<array<string, mixed>>
     */
    private static function extractDiagnosisRows(
        array $datosExtraidos,
        ?EncounterDefinition $configuracion = null
    ): array {
        $rows = [];
        foreach (self::diagnosisPayloadKeys($datosExtraidos, $configuracion) as $key) {
            $payload = $datosExtraidos[$key] ?? null;
            if (!is_array($payload)) {
                continue;
            }
            foreach ($payload as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                } elseif (is_string($row) && trim($row) !== '') {
                    $rows[] = ['texto' => trim($row)];
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     * @return list<string|int>
     */
    private static function diagnosisPayloadKeys(array $datosExtraidos, ?EncounterDefinition $configuracion): array
    {
        $keys = [];
        if ($configuracion !== null) {
            foreach (EncounterDefinition::getCategoriasParaPrompt($configuracion) as $categoria) {
                if (($categoria['modelo'] ?? '') !== 'DiagnosticoConsulta') {
                    continue;
                }
                $titulo = $categoria['titulo'] ?? null;
                if (is_string($titulo) && $titulo !== '' && isset($datosExtraidos[$titulo])) {
                    $keys[] = $titulo;
                }
            }
        }

        if (isset($datosExtraidos['DiagnosticoConsulta'])) {
            $keys[] = 'DiagnosticoConsulta';
        }

        if ($keys === []) {
            foreach ($datosExtraidos as $key => $payload) {
                if (!is_array($payload) || !is_string($key)) {
                    continue;
                }
                if (stripos($key, 'diagnost') !== false) {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<array{
     *   termino: string,
     *   codigo_cie10: string|null,
     *   codigo_snomed: string|null,
     *   rol: string,
     *   justificacion: string
     * }>
     */
    private static function inferCodesWithIa(
        string $clinicalText,
        string $diagnosisHints,
        int $subjectPersonaId
    ): array {
        $patientBlock = '';
        if ($subjectPersonaId > 0) {
            $patientBlock = (new PatientAiContextBuilder())->build(
                $subjectPersonaId,
                PatientAiContextBuilder::PROFILE_ENCOUNTER
            );
        }

        $prompt = ClinicalTextIaMetadata::buildEncounterAutomaticCodingPrompt(
            $clinicalText,
            $patientBlock,
            $diagnosisHints
        );

        $raw = IAManager::consultarIA($prompt, self::IA_CONTEXT, 'analysis');

        return self::normalizeDiagnosticsPayload($raw);
    }

    /**
     * @param mixed $raw
     * @return list<array{termino: string, codigo_cie10: string|null, codigo_snomed: string|null, rol: string, justificacion: string}>
     */
    private static function normalizeDiagnosticsPayload($raw): array
    {
        $data = $raw;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $data = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($data)) {
            return [];
        }

        $max = ClinicalTextIaMetadata::encounterAutomaticCodingMaxDiagnosticos();
        $out = [];
        foreach ($data['diagnosticos'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $term = trim((string) ($row['termino'] ?? ''));
            $cie10 = self::normalizeCie10($row['codigo_cie10'] ?? null);
            $snomed = self::normalizeSnomed($row['codigo_snomed'] ?? null);
            if ($term === '' || ($cie10 === null && $snomed === null)) {
                continue;
            }
            $rol = strtolower(trim((string) ($row['rol'] ?? 'secundario')));
            if ($rol !== 'principal') {
                $rol = 'secundario';
            }
            $out[] = [
                'termino' => $term,
                'codigo_cie10' => $cie10,
                'codigo_snomed' => $snomed,
                'rol' => $rol,
                'justificacion' => trim((string) ($row['justificacion'] ?? '')),
            ];
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    private static function normalizeCie10($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $code = strtoupper(str_replace(' ', '', trim((string) $value)));
        if (!preg_match('/^[A-Z][0-9]{2}(\.[0-9]{1,2})?$/', $code)) {
            return null;
        }

        return $code;
    }

    private static function normalizeSnomed($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $code = preg_replace('/\D/', '', (string) $value);
        if ($code === null || $code === '' || strlen($code) < 6 || strlen($code) > 18) {
            return null;
        }

        return $code;
    }

    /**
     * @param list<array{termino: string, codigo_cie10: string|null, codigo_snomed: string|null, rol: string, justificacion: string}> $diagnostics
     */
    private static function persistDiagnostics(Encounter $encounter, array $diagnostics): int
    {
        $existingCodes = Condition::find()
            ->select(['code', 'code_system'])
            ->where(['encounter_id' => $encounter->id])
            ->asArray()
            ->all();
        $seen = [];
        foreach ($existingCodes as $row) {
            $seen[self::codeKey((string) $row['code'], (string) ($row['code_system'] ?? ''))] = true;
        }

        $saved = 0;
        foreach ($diagnostics as $dx) {
            $noteParts = [];
            if ($dx['justificacion'] !== '') {
                $noteParts[] = $dx['justificacion'];
            }
            $noteParts[] = 'codificacion-automatica-ia';
            $note = implode(' | ', $noteParts);

            if ($dx['codigo_cie10'] !== null) {
                $saved += self::saveCondition(
                    $encounter,
                    $dx['codigo_cie10'],
                    self::CODE_SYSTEM_ICD10,
                    $dx['termino'],
                    $dx['rol'],
                    $note,
                    $seen
                );
            }
            if ($dx['codigo_snomed'] !== null) {
                $saved += self::saveCondition(
                    $encounter,
                    $dx['codigo_snomed'],
                    self::CODE_SYSTEM_SNOMED,
                    $dx['termino'],
                    $dx['rol'],
                    $note,
                    $seen
                );
            }
        }

        if ($saved > 0) {
            Yii::info(
                "Encounter {$encounter->id}: {$saved} condición(es) codificada(s) automáticamente",
                'encounter-codificacion-automatica'
            );
        }

        return $saved;
    }

    /**
     * @param array<string, bool> $seen
     */
    private static function saveCondition(
        Encounter $encounter,
        string $code,
        string $codeSystem,
        string $display,
        string $rol,
        string $note,
        array &$seen
    ): int {
        $key = self::codeKey($code, $codeSystem);
        if (isset($seen[$key])) {
            return 0;
        }

        if ($encounter->id <= 0 || !Encounter::find()->where(['id' => $encounter->id])->exists()) {
            Yii::warning(
                'EncounterAutomaticCodingService: encounter inexistente id=' . ($encounter->id ?? 'null'),
                'encounter-codificacion-automatica'
            );

            return 0;
        }

        $condition = new Condition();
        $condition->encounter_id = $encounter->id;
        $condition->subject_persona_id = $encounter->subject_persona_id;
        $condition->code = $code;
        $condition->code_system = $codeSystem;
        $condition->display = $display;
        $condition->clinical_status = DiagnosticoConsulta::CLINICAL_STATUS_ACTIVE;
        $condition->verification_status = DiagnosticoConsulta::VERIFICATION_STATUS_PROVISIONAL;
        $condition->diagnosis_role = $rol === 'principal' ? 'principal' : 'secondary';
        $condition->note = $note;
        $condition->recorded_date = date('Y-m-d H:i:s');
        if (!$condition->save(false)) {
            Yii::warning(
                'No se pudo persistir condición auto-codificada: ' . json_encode($condition->errors),
                'encounter-codificacion-automatica'
            );

            return 0;
        }

        $seen[$key] = true;

        return 1;
    }

    private static function codeKey(string $code, string $codeSystem): string
    {
        return $codeSystem . '#' . $code;
    }
}
