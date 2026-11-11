<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Clinical\Encounter\Domain\AppointmentReasonQuestionnaireCatalog;

/**
 * Cuestionario pre-turno de motivos ({@see AppointmentReasonQuestionnaireCatalog}).
 *
 * Selecciona las preguntas que corresponden al turno según la trayectoria del triage de reserva y
 * descarta las que el paciente ya respondió (en el triage o en el propio cuestionario).
 */
final class AppointmentReasonQuestionnaireCatalogService
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function isEnabled(): bool
    {
        return (bool) (self::load()['enabled'] ?? false);
    }

    public function title(): string
    {
        $title = trim((string) (self::load()['title'] ?? ''));

        return $title !== '' ? $title : 'Prepará tu consulta';
    }

    public function intro(): string
    {
        return trim((string) (self::load()['intro'] ?? ''));
    }

    /**
     * @return array{id: string, text: string, hint: string, max_length: int}
     */
    public function closing(): array
    {
        $closing = self::load()['closing'] ?? [];
        if (!is_array($closing)) {
            $closing = [];
        }

        return [
            'id' => AppointmentReasonQuestionnaireCatalog::CLOSING_ID,
            'text' => trim((string) ($closing['text'] ?? '¿Querés sumar algo más?')),
            'hint' => trim((string) ($closing['hint'] ?? '')),
            'max_length' => $this->closingMaxLength(),
        ];
    }

    public function closingMaxLength(): int
    {
        $raw = (int) (self::load()['closing']['max_length'] ?? 0);

        return $raw > 0 ? $raw : AppointmentReasonQuestionnaireCatalog::CLOSING_MAX_LENGTH;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function questions(): array
    {
        $raw = self::load()['questions'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            $question = is_array($row) ? $this->normalizeQuestion($row) : null;
            if ($question !== null) {
                $out[] = $question;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function questionById(string $questionId): ?array
    {
        $questionId = trim($questionId);
        if ($questionId === '') {
            return null;
        }
        foreach ($this->questions() as $question) {
            if (($question['id'] ?? '') === $questionId) {
                return $question;
            }
        }

        return null;
    }

    public function isClosing(string $questionId): bool
    {
        return trim($questionId) === AppointmentReasonQuestionnaireCatalog::CLOSING_ID;
    }

    /**
     * @param array<string, mixed> $question
     * @return array{id: string, label: string}|null
     */
    public function resolveOption(array $question, string $optionId): ?array
    {
        $optionId = trim($optionId);
        if ($optionId === '') {
            return null;
        }
        if ($optionId === AppointmentReasonQuestionnaireCatalog::OTHER_OPTION_ID) {
            return !empty($question['allow_other'])
                ? ['id' => AppointmentReasonQuestionnaireCatalog::OTHER_OPTION_ID, 'label' => 'Otra']
                : null;
        }
        foreach ($question['options'] ?? [] as $option) {
            if (($option['id'] ?? '') === $optionId) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $question
     */
    public function optionLabel(array $question, ?string $optionId): string
    {
        if ($optionId === null || trim($optionId) === '') {
            return '';
        }
        $option = $this->resolveOption($question, (string) $optionId);

        return $option !== null ? (string) $option['label'] : '';
    }

    /**
     * Preguntas a mostrar: las que aplican al triage y aún no fueron respondidas.
     *
     * @param array<string, string> $triageFields valores del draft del turno (p. ej. triage_zona)
     * @param list<string> $answeredIds
     * @return list<array<string, mixed>>
     */
    public function pendingQuestions(array $triageFields, array $answeredIds): array
    {
        $answered = array_flip(array_map('strval', $answeredIds));
        $out = [];
        foreach ($this->questions() as $question) {
            $id = (string) ($question['id'] ?? '');
            if ($id === '' || isset($answered[$id])) {
                continue;
            }
            if (!$this->appliesToTriage($question, $triageFields)) {
                continue;
            }
            $out[] = $question;
        }

        return $out;
    }

    /**
     * La pregunta corresponde a este turno y el triage no la respondió ya.
     *
     * @param array<string, mixed> $question
     * @param array<string, string> $triageFields
     */
    public function appliesToTriage(array $question, array $triageFields): bool
    {
        return $this->matchesTriage($question, $triageFields)
            && !$this->answeredByTriage($question, $triageFields);
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function normalizeQuestion(array $row): ?array
    {
        $id = trim((string) ($row['id'] ?? ''));
        $text = trim((string) ($row['text'] ?? ''));
        if ($id === '' || $text === '' || $id === AppointmentReasonQuestionnaireCatalog::CLOSING_ID) {
            return null;
        }

        $options = [];
        $rawOptions = $row['options'] ?? [];
        if (is_array($rawOptions)) {
            foreach ($rawOptions as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $optionId = trim((string) ($option['id'] ?? ''));
                $label = trim((string) ($option['label'] ?? ''));
                if ($optionId === '' || $label === '') {
                    continue;
                }
                $options[] = ['id' => $optionId, 'label' => $label];
            }
        }
        if ($options === []) {
            return null;
        }

        $otherMax = (int) ($row['other_max_length'] ?? 0);
        $skipFields = $row['skip_when_triage_fields'] ?? [];

        return [
            'id' => $id,
            'text' => $text,
            'options' => $options,
            'allow_other' => !empty($row['allow_other']),
            'other_max_length' => $otherMax > 0
                ? $otherMax
                : AppointmentReasonQuestionnaireCatalog::OTHER_MAX_LENGTH,
            'only_when' => is_array($row['only_when'] ?? null) ? $row['only_when'] : [],
            'skip_when_triage_fields' => is_array($skipFields)
                ? array_values(array_map('strval', $skipFields))
                : [],
        ];
    }

    /**
     * @param array<string, mixed> $question
     * @param array<string, string> $triageFields
     */
    private function matchesTriage(array $question, array $triageFields): bool
    {
        $onlyWhen = $question['only_when'] ?? null;
        if (!is_array($onlyWhen) || $onlyWhen === []) {
            return true;
        }

        foreach ($onlyWhen as $field => $codes) {
            $value = trim((string) ($triageFields[(string) $field] ?? ''));
            $allowed = is_array($codes) ? array_map('strval', $codes) : [(string) $codes];
            if ($value === '' || !in_array($value, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $question
     * @param array<string, string> $triageFields
     */
    private function answeredByTriage(array $question, array $triageFields): bool
    {
        $fields = $question['skip_when_triage_fields'] ?? [];
        if (!is_array($fields)) {
            return false;
        }
        foreach ($fields as $field) {
            if (trim((string) ($triageFields[(string) $field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $data = AppointmentReasonQuestionnaireCatalog::config();
        if (!is_array($data)) {
            throw new \RuntimeException('Catálogo AppointmentReasonQuestionnaire inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
