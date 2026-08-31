<?php

namespace common\components\Domain\Clinical\AiContext;

use common\components\Domain\Clinical\Enum\CarePlanCategory;
use common\components\Domain\Clinical\Enum\CarePlanStatus;
use common\components\Domain\Clinical\Enum\RequestStatus;
use common\components\Domain\Clinical\Service\CarePlanPresentationService;
use common\components\Domain\Clinical\Service\EncounterLifecycleService;
use common\components\Domain\Clinical\Service\EpisodeOfCareService;
use common\components\Domain\Person\Service\PersonaAsistentePreferenciasService;
use common\models\Clinical\AllergyIntolerance;
use common\models\Clinical\CarePlan;
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
    public const PROFILE_GUIDE = 'guide';

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
        self::PROFILE_GUIDE => [
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

    public function build(
        int $subjectPersonaId,
        string $profile = self::PROFILE_ENCOUNTER,
        ?string $episodeParent = null,
        ?int $episodeParentId = null
    ): string {
        if ($subjectPersonaId <= 0 || !$this->canAccess($subjectPersonaId, $profile)) {
            return '';
        }

        if (
            ($profile === self::PROFILE_CONVERSATIONAL || $profile === self::PROFILE_GUIDE)
            && !(new PersonaAsistentePreferenciasService())->usaResumenHcEnAsistente($subjectPersonaId)
        ) {
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

        $prior = $this->collectPriorEpisodeEvolutions(
            $subjectPersonaId,
            $episodeParent,
            $episodeParentId
        );
        if ($prior !== []) {
            $data['prior_evolutions'] = $prior;
        }

        $carePlan = $this->collectInpatientCarePlan($episodeParent, $episodeParentId);
        if ($carePlan !== []) {
            $data['care_plan'] = $carePlan;
        }

        return self::formatBlock($data, $profile, $this->maxChars());
    }

    /**
     * @param array{
     *   demographics?: array{edad?: int|string|null, sexo?: string|null},
     *   conditions?: list<string>,
     *   medications?: list<string>,
     *   allergies?: list<string>,
     *   prior_evolutions?: list<string>,
     *   care_plan?: list<string>
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

        if (
            $profile !== self::PROFILE_CONVERSATIONAL
            && $profile !== self::PROFILE_GUIDE
            || ($data['medications'] ?? []) !== []
        ) {
            self::appendListSection($lines, 'Medicación activa', $data['medications'] ?? [], 'Sin medicación activa registrada.');
        }

        $priors = $data['prior_evolutions'] ?? [];
        if (is_array($priors) && $priors !== []) {
            $lines[] = '- Evoluciones previas del episodio (no reextraer lo ya documentado; priorizar cambios clínicos):';
            foreach ($priors as $prior) {
                $lines[] = '  · ' . $prior;
            }
        }

        $carePlan = $data['care_plan'] ?? [];
        if (is_array($carePlan) && $carePlan !== []) {
            $lines[] = '- Plan de cuidado indicado (seguir / documentar cumplimiento; no reindicar lo ya activo):';
            foreach ($carePlan as $item) {
                $lines[] = '  · ' . $item;
            }
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

    /**
     * Candado por perfil. Conversacional = solo la persona de sesión.
     * Motivos/captura: uno mismo, staff con PES, o job de consola (sin sesión web).
     */
    public static function mayAccessSubject(
        int $subjectPersonaId,
        string $profile,
        bool $isConsole,
        int $sessionPersonaId,
        int $sessionPesId
    ): bool {
        if ($subjectPersonaId < 1) {
            return false;
        }

        $isSelf = $sessionPersonaId > 0 && $sessionPersonaId === $subjectPersonaId;

        if (
            $profile === self::PROFILE_CONVERSATIONAL
            || $profile === self::PROFILE_GUIDE
        ) {
            return $isSelf;
        }

        if ($isConsole) {
            return true;
        }

        if ($isSelf) {
            return true;
        }

        return $sessionPesId > 0;
    }

    private function canAccess(int $subjectPersonaId, string $profile): bool
    {
        $isConsole = Yii::$app instanceof \yii\console\Application;
        $sessionPersona = 0;
        $sessionPes = 0;

        if (!$isConsole && Yii::$app->has('user', true)) {
            $user = Yii::$app->user;
            if ($user !== null && method_exists($user, 'getIsGuest') && !$user->getIsGuest()) {
                if (method_exists($user, 'getIdPersona')) {
                    $sessionPersona = (int) $user->getIdPersona();
                }
                if (method_exists($user, 'getIdProfesionalEfectorServicio')) {
                    $sessionPes = (int) $user->getIdProfesionalEfectorServicio();
                }
            }
        }

        return self::mayAccessSubject(
            $subjectPersonaId,
            $profile,
            $isConsole,
            $sessionPersona,
            $sessionPes
        );
    }

    /**
     * @return list<string>
     */
    private function collectPriorEpisodeEvolutions(
        int $subjectPersonaId,
        ?string $episodeParent,
        ?int $episodeParentId
    ): array {
        $parent = strtoupper(trim((string) $episodeParent));
        $parentId = (int) ($episodeParentId ?? 0);
        if (
            $parentId <= 0
            || !in_array($parent, [Encounter::PARENT_INTERNACION, Encounter::PARENT_GUARDIA], true)
        ) {
            return [];
        }

        $rows = Encounter::find()
            ->select(['note', 'reason_text', 'period_start', 'created_at'])
            ->where([
                'parent_id' => $parentId,
                'subject_persona_id' => $subjectPersonaId,
                'deleted_at' => null,
            ])
            ->andWhere([
                'or',
                ['parent_type' => $parent],
                ['parent_type' => Encounter::PARENT_CLASSES[$parent] ?? '__none__'],
            ])
            ->andWhere(['<>', 'status', \common\components\Domain\Clinical\Enum\EncounterStatus::IN_PROGRESS])
            ->orderBy(['id' => SORT_DESC])
            ->limit(3)
            ->asArray()
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $note = trim((string) ($row['note'] ?? ''));
            if ($note === '') {
                $note = trim((string) ($row['reason_text'] ?? ''));
            }
            if ($note === '') {
                continue;
            }
            if (mb_strlen($note) > 280) {
                $note = rtrim(mb_substr($note, 0, 279)) . '…';
            }
            $when = trim((string) ($row['period_start'] ?? $row['created_at'] ?? ''));
            $out[] = ($when !== '' ? $when . ' — ' : '') . $note;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function collectInpatientCarePlan(?string $episodeParent, ?int $episodeParentId): array
    {
        $parent = strtoupper(trim((string) $episodeParent));
        $parentId = (int) $episodeParentId;
        if ($parent !== Encounter::PARENT_INTERNACION || $parentId <= 0) {
            return [];
        }
        $episode = (new EpisodeOfCareService())->findActiveForInternacion($parentId);
        if ($episode === null) {
            return [];
        }
        $plan = CarePlan::find()
            ->andWhere([
                'episode_of_care_id' => $episode->id,
                'category' => CarePlanCategory::INPATIENT,
            ])
            ->andWhere(['status' => [CarePlanStatus::DRAFT, CarePlanStatus::ACTIVE, CarePlanStatus::ON_HOLD]])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();
        if (!$plan instanceof CarePlan) {
            return [];
        }
        $summary = (new CarePlanPresentationService())->toPatientSummary($plan, true, 12);
        $lines = [];
        if (is_array($summary['activitySummaries'] ?? null)) {
            foreach ($summary['activitySummaries'] as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $lines[] = trim($line);
                }
            }
        }

        return $lines;
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
