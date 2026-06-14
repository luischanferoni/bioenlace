<?php

namespace common\components\Clinical\AiContext;

use common\components\Clinical\Enum\RequestStatus;
use common\components\Clinical\Service\EncounterLifecycleService;
use common\models\Clinical\AllergyIntolerance;
use common\models\Clinical\Encounter;
use common\models\Clinical\MedicationRequest;
use common\models\DiagnosticoConsultaRepository as DCRepo;
use common\models\Person\Persona;
use Yii;

/**
 * Bloque acotado de contexto clínico del paciente para prompts de IA (~300–600 tokens).
 * No incluye HC completa ni resúmenes IA de atenciones previas.
 */
final class PatientAiContextBuilder
{
    public const PROFILE_ENCOUNTER = 'encounter';
    public const PROFILE_MOTIVOS = 'motivos';
    public const PROFILE_CONVERSATIONAL = 'conversational';

    public const BLOCK_HEADER = 'Contexto clínico del paciente (referencia; no inventar ni extraer al JSON salvo indicación):';

    private const DEFAULT_MAX_CHARS = 2400;

    /** @var array<string, array{max_conditions: int, max_medications: int, max_allergies: int}> */
    private const DEFAULT_PROFILE_LIMITS = [
        self::PROFILE_ENCOUNTER => [
            'max_conditions' => 8,
            'max_medications' => 8,
            'max_allergies' => 12,
        ],
        self::PROFILE_MOTIVOS => [
            'max_conditions' => 6,
            'max_medications' => 6,
            'max_allergies' => 12,
        ],
        self::PROFILE_CONVERSATIONAL => [
            'max_conditions' => 4,
            'max_medications' => 4,
            'max_allergies' => 8,
        ],
    ];

    /**
     * Resuelve id_persona desde body de captura clínica (encounter / sesión).
     *
     * @param array<string, mixed> $body
     */
    public static function resolveSubjectPersonaIdFromBody(array $body): ?int
    {
        $id = (new EncounterLifecycleService())->resolveSubjectPersonaId($body);
        if ($id !== null && $id > 0) {
            return $id;
        }

        $encounterId = (int) ($body['encounter_id'] ?? $body['id_consulta'] ?? 0);
        if ($encounterId <= 0) {
            return null;
        }
        $encounter = Encounter::findOne($encounterId);
        if ($encounter === null) {
            return null;
        }

        return (int) $encounter->subject_persona_id;
    }

    public function build(int $subjectPersonaId, string $profile = self::PROFILE_ENCOUNTER): string
    {
        if ($subjectPersonaId <= 0 || !$this->canAccess($subjectPersonaId)) {
            return '';
        }

        $persona = Persona::findOne(['id_persona' => $subjectPersonaId]);
        if ($persona === null) {
            return '';
        }

        $limits = $this->profileLimits($profile);
        $data = [
            'demographics' => $this->collectDemographics($persona),
            'conditions' => $this->collectConditions($subjectPersonaId, $limits['max_conditions']),
            'medications' => $this->collectMedications($subjectPersonaId, $limits['max_medications']),
            'allergies' => $this->collectAllergies($subjectPersonaId, $limits['max_allergies']),
        ];

        return self::formatBlock($data, $profile, $this->maxChars());
    }

    /**
     * @param array{
     *   demographics?: array{edad?: int|string|null, sexo?: string|null},
     *   conditions?: list<string>,
     *   medications?: list<string>,
     *   allergies?: list<string>
     * } $data
     */
    public static function formatBlock(array $data, string $profile, int $maxChars): string
    {
        $lines = [self::BLOCK_HEADER];

        $demo = $data['demographics'] ?? [];
        $demoParts = [];
        if (!empty($demo['edad'])) {
            $demoParts[] = 'Edad: ' . $demo['edad'] . ' años';
        }
        if (!empty($demo['sexo'])) {
            $demoParts[] = 'Sexo: ' . $demo['sexo'];
        }
        if ($demoParts !== []) {
            $lines[] = '- ' . implode('; ', $demoParts);
        }

        self::appendListSection($lines, 'Alergias/intolerancias activas', $data['allergies'] ?? [], 'Sin alergias registradas.');
        self::appendListSection($lines, 'Condiciones activas', $data['conditions'] ?? [], 'Sin condiciones activas registradas.');

        if ($profile !== self::PROFILE_CONVERSATIONAL || ($data['medications'] ?? []) !== []) {
            self::appendListSection($lines, 'Medicación activa', $data['medications'] ?? [], 'Sin medicación activa registrada.');
        }

        $block = implode("\n", $lines);
        if (strlen($block) <= $maxChars) {
            return $block;
        }

        return rtrim(substr($block, 0, max(0, $maxChars - 1))) . '…';
    }

    /**
     * @param list<string> $items
     * @param list<string> $lines
     */
    private static function appendListSection(array &$lines, string $title, array $items, string $emptyLabel): void
    {
        $lines[] = "- {$title}:";
        if ($items === []) {
            $lines[] = "  · {$emptyLabel}";

            return;
        }
        foreach ($items as $item) {
            $lines[] = '  · ' . $item;
        }
    }

    /**
     * @return array{edad?: int|string|null, sexo?: string|null}
     */
    private function collectDemographics(Persona $persona): array
    {
        $edad = null;
        if (method_exists($persona, 'getEdad')) {
            $edad = $persona->getEdad();
        } elseif (isset($persona->edad)) {
            $edad = $persona->edad;
        }

        $sexo = null;
        if (method_exists($persona, 'getSexoTexto')) {
            $sexo = $persona->getSexoTexto();
        } elseif (method_exists($persona, 'getSexoLetra')) {
            $sexo = $persona->getSexoLetra();
        }

        return [
            'edad' => $edad,
            'sexo' => $sexo,
        ];
    }

    /**
     * @return list<string>
     */
    private function collectConditions(int $subjectPersonaId, int $limit): array
    {
        [$activas, $cronicas] = DCRepo::getCondicionesPaciente($subjectPersonaId);
        $seen = [];
        $out = [];

        foreach (array_merge($cronicas, $activas) as $cond) {
            $term = isset($cond->codigoSnomed) ? trim((string) $cond->codigoSnomed->term) : '';
            if ($term === '' || isset($seen[$term])) {
                continue;
            }
            $seen[$term] = true;
            $suffix = ($cond->cronico ?? '') === 'SI' ? ' (crónica)' : '';
            $out[] = $term . $suffix;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function collectMedications(int $subjectPersonaId, int $limit): array
    {
        $rows = MedicationRequest::find()
            ->where([
                'subject_persona_id' => $subjectPersonaId,
                'status' => RequestStatus::ACTIVE,
            ])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['authored_on' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();

        $out = [];
        foreach ($rows as $mr) {
            $name = trim((string) ($mr->medication_display ?? ''));
            if ($name === '') {
                $name = trim((string) ($mr->medication_code ?? ''));
            }
            if ($name === '') {
                continue;
            }
            $dosage = trim((string) ($mr->dosage_text ?? ''));
            $out[] = $dosage !== '' ? "{$name} — {$dosage}" : $name;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function collectAllergies(int $subjectPersonaId, int $limit): array
    {
        $seen = [];
        $out = [];

        foreach (AllergyIntolerance::findActiveBySubject($subjectPersonaId, $limit) as $ai) {
            $term = trim((string) ($ai->display ?? ''));
            if ($term === '' && !empty($ai->code)) {
                $term = (string) $ai->code;
            }
            if ($term === '' || isset($seen[$term])) {
                continue;
            }
            $seen[$term] = true;
            $out[] = $this->formatAllergyLabel($term, $ai->type ?? null, $ai->criticality ?? null);
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    private function formatAllergyLabel(string $term, ?string $tipo, ?string $criticidad): string
    {
        $parts = [$term];
        if ($tipo !== null && $tipo !== '') {
            $parts[] = $tipo;
        }
        if ($criticidad !== null && $criticidad !== '' && $criticidad !== 'unable-to-assess') {
            $parts[] = 'criticidad ' . $criticidad;
        }

        return implode(', ', $parts);
    }

    private function canAccess(int $subjectPersonaId): bool
    {
        $sessionPersona = (int) Yii::$app->user->getIdPersona();
        if ($sessionPersona > 0 && $sessionPersona === $subjectPersonaId) {
            return true;
        }

        $idPes = (int) Yii::$app->user->getIdProfesionalEfectorServicio();
        if ($idPes > 0) {
            return true;
        }

        return false;
    }

    /**
     * @return array{max_conditions: int, max_medications: int, max_allergies: int}
     */
    private function profileLimits(string $profile): array
    {
        $cfg = Yii::$app->params['patient_ai_context']['profiles'] ?? [];
        $defaults = self::DEFAULT_PROFILE_LIMITS[$profile]
            ?? self::DEFAULT_PROFILE_LIMITS[self::PROFILE_ENCOUNTER];

        if (!is_array($cfg) || !isset($cfg[$profile]) || !is_array($cfg[$profile])) {
            return $defaults;
        }

        return array_merge($defaults, $cfg[$profile]);
    }

    private function maxChars(): int
    {
        $max = Yii::$app->params['patient_ai_context']['max_chars'] ?? self::DEFAULT_MAX_CHARS;

        return max(400, (int) $max);
    }
}
