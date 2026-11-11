<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Clinical\Encounter\Domain\AppointmentReasonQuestionnaireCatalog;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterReasonQuestionnaireAnswer;

/**
 * Cuestionario pre-turno: estado, respuestas y cierre libre.
 *
 * Auth HTTP en el controller; aquí ventana, validación contra el catálogo y persistencia.
 * Los fallos lanzan {@see ApiException}.
 */
final class AppointmentReasonQuestionnaireService
{
    private AppointmentReasonQuestionnaireCatalogService $catalog;
    private AppointmentReasonInitialContextService $initialContext;

    public function __construct(
        ?AppointmentReasonQuestionnaireCatalogService $catalog = null,
        ?AppointmentReasonInitialContextService $initialContext = null
    ) {
        $this->catalog = $catalog ?? new AppointmentReasonQuestionnaireCatalogService();
        $this->initialContext = $initialContext ?? new AppointmentReasonInitialContextService();
    }

    /**
     * @return array<string, mixed>
     */
    public function state(Encounter $encounter): array
    {
        $encounterId = (int) $encounter->id;
        $answers = EncounterReasonQuestionnaireAnswer::findForEncounter($encounterId);
        $answeredIds = array_map(static fn (EncounterReasonQuestionnaireAnswer $a): string => (string) $a->question_id, $answers);

        $pending = $this->catalog->pendingQuestions($this->initialContext->triageFields($encounter), $answeredIds);
        $completed = $this->isClosingAnswered($answeredIds);
        $window = AppointmentReasonWindowService::apiState($encounterId);

        return array_merge(
            [
                'kind' => 'motivos_cuestionario',
                'encounter_id' => $encounterId,
                'title' => $this->catalog->title(),
                'intro' => $this->catalog->intro(),
                'questions' => array_map([$this, 'serializeQuestion'], $pending),
                'answered' => $this->serializeAnswers($answers),
                'pending_count' => count($pending),
                'closing' => !$completed && $pending === [] && !empty($window['input_abierto'])
                    ? $this->catalog->closing()
                    : null,
                'completed' => $completed,
            ],
            $window
        );
    }

    /**
     * Guarda una respuesta (o el cierre libre). Devuelve el estado recalculado del cuestionario.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function answer(Encounter $encounter, array $body, int $userId): array
    {
        $this->assertWindowOpen((int) $encounter->id);

        $questionId = trim((string) ($body['question_id'] ?? ''));
        if ($questionId === '') {
            throw new ApiException('reason_question_invalid');
        }

        $optionId = trim((string) ($body['option_id'] ?? ''));
        $freeText = trim((string) ($body['texto'] ?? $body['text'] ?? ''));

        if ($this->catalog->isClosing($questionId)) {
            $this->saveClosing((int) $encounter->id, $freeText, $userId);

            return $this->state($encounter);
        }

        $question = $this->catalog->questionById($questionId);
        if ($question === null) {
            throw new ApiException('reason_question_invalid');
        }
        $this->assertQuestionAvailable($encounter, $question);

        $option = $this->catalog->resolveOption($question, $optionId);
        if ($option === null) {
            throw new ApiException('reason_question_option_invalid');
        }

        $textoLibre = null;
        if ($option['id'] === AppointmentReasonQuestionnaireCatalog::OTHER_OPTION_ID) {
            if ($freeText === '') {
                throw new ApiException('reason_answer_text_required');
            }
            $max = (int) $question['other_max_length'];
            if (mb_strlen($freeText) > $max) {
                throw new ApiException('reason_answer_too_long', ['max_length' => $max]);
            }
            $textoLibre = $freeText;
        }

        $this->persist((int) $encounter->id, $questionId, (string) $option['id'], $textoLibre, $userId);

        return $this->state($encounter);
    }

    /**
     * Texto del cuestionario para el resumen del médico.
     *
     * @return array{narrative: string, completed: bool}
     */
    public function narrativeForEncounter(Encounter $encounter): array
    {
        $answers = EncounterReasonQuestionnaireAnswer::findForEncounter((int) $encounter->id);
        if ($answers === []) {
            return ['narrative' => '', 'completed' => false];
        }

        $byQuestion = [];
        foreach ($answers as $answer) {
            $byQuestion[(string) $answer->question_id] = $answer;
        }

        $lines = [];
        foreach ($this->catalog->questions() as $question) {
            $answer = $byQuestion[(string) $question['id']] ?? null;
            if ($answer === null) {
                continue;
            }
            $line = '- ' . $question['text'] . ' → ' . $this->catalog->optionLabel($question, $answer->option_id);
            $extra = trim((string) $answer->texto_libre);
            if ($extra !== '') {
                $line .= ' (' . $extra . ')';
            }
            $lines[] = $line;
        }

        $closing = $byQuestion[AppointmentReasonQuestionnaireCatalog::CLOSING_ID] ?? null;
        $closingText = $closing !== null ? trim((string) $closing->texto_libre) : '';
        if ($closingText !== '') {
            $lines[] = '- Agrega el paciente: ' . $closingText;
        }

        return [
            'narrative' => implode("\n", $lines),
            'completed' => $closing !== null,
        ];
    }

    public function isCompleted(int $encounterId): bool    {
        if ($encounterId <= 0) {
            return false;
        }

        return EncounterReasonQuestionnaireAnswer::find()
            ->where(['encounter_id' => $encounterId, 'question_id' => AppointmentReasonQuestionnaireCatalog::CLOSING_ID])
            ->exists();
    }

    public function hasAnswers(int $encounterId): bool
    {
        if ($encounterId <= 0) {
            return false;
        }

        return EncounterReasonQuestionnaireAnswer::find()
            ->where(['encounter_id' => $encounterId])
            ->exists();
    }

    /**
     * Respuestas en orden de catálogo para la vista del staff.
     *
     * @return array{answers: list<array<string, mixed>>, completed: bool}
     */
    public function answersView(Encounter $encounter): array
    {
        $answers = EncounterReasonQuestionnaireAnswer::findForEncounter((int) $encounter->id);

        return [
            'answers' => $this->serializeAnswers($answers),
            'completed' => $this->isCompleted((int) $encounter->id),
        ];
    }

    /**
     * @param array<string, mixed> $question
     */
    private function assertQuestionAvailable(Encounter $encounter, array $question): void
    {
        if (!$this->catalog->appliesToTriage($question, $this->initialContext->triageFields($encounter))) {
            throw new ApiException('reason_question_not_available');
        }
    }

    private function saveClosing(int $encounterId, string $freeText, int $userId): void
    {
        $max = $this->catalog->closingMaxLength();
        if (mb_strlen($freeText) > $max) {
            throw new ApiException('reason_answer_too_long', ['max_length' => $max]);
        }

        $this->persist($encounterId, AppointmentReasonQuestionnaireCatalog::CLOSING_ID, null, $freeText, $userId);
    }

    private function persist(
        int $encounterId,
        string $questionId,
        ?string $optionId,
        ?string $freeText,
        int $userId
    ): void {
        $answer = EncounterReasonQuestionnaireAnswer::findOne([
            'encounter_id' => $encounterId,
            'question_id' => $questionId,
        ]);
        if ($answer === null) {
            $answer = new EncounterReasonQuestionnaireAnswer();
            $answer->encounter_id = $encounterId;
            $answer->question_id = $questionId;
        }

        $answer->option_id = $optionId;
        $answer->texto_libre = $freeText !== null && $freeText !== '' ? $freeText : null;
        $answer->answered_by_user_id = $userId > 0 ? $userId : null;

        if (!$answer->save()) {
            throw new ApiException('reason_answer_save_failed', [
                'detail' => implode(', ', $answer->getFirstErrors()),
            ]);
        }
    }

    /**
     * @throws ApiException
     */
    private function assertWindowOpen(int $encounterId): void
    {
        if (AppointmentReasonWindowService::isInputOpen($encounterId)) {
            return;
        }

        throw new ApiException(
            'reason_window_closed',
            ['minutes' => AppointmentReasonWindowService::minutesBeforeClose()],
            [],
            null,
            AppointmentReasonWindowService::apiState($encounterId)
        );
    }

    /**
     * @param EncounterReasonQuestionnaireAnswer[] $answers
     * @return list<array<string, mixed>>
     */
    private function serializeAnswers(array $answers): array
    {
        $out = [];
        foreach ($answers as $answer) {
            $questionId = (string) $answer->question_id;
            if ($this->catalog->isClosing($questionId)) {
                $closing = $this->catalog->closing();
                $out[] = [
                    'question_id' => $questionId,
                    'question_text' => $closing['text'],
                    'option_id' => null,
                    'option_label' => '',
                    'texto_libre' => (string) $answer->texto_libre,
                    'answered_at' => (string) $answer->created_at,
                ];
                continue;
            }

            $question = $this->catalog->questionById($questionId);
            if ($question === null) {
                continue;
            }
            $out[] = [
                'question_id' => $questionId,
                'question_text' => (string) $question['text'],
                'option_id' => (string) $answer->option_id,
                'option_label' => $this->catalog->optionLabel($question, $answer->option_id),
                'texto_libre' => (string) $answer->texto_libre,
                'answered_at' => (string) $answer->created_at,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $question
     * @return array<string, mixed>
     */
    private function serializeQuestion(array $question): array
    {
        return [
            'id' => (string) $question['id'],
            'text' => (string) $question['text'],
            'options' => $question['options'],
            'allow_other' => (bool) $question['allow_other'],
            'other_max_length' => (int) $question['other_max_length'],
        ];
    }

    /**
     * @param list<string> $answeredIds
     */
    private function isClosingAnswered(array $answeredIds): bool
    {
        return in_array(AppointmentReasonQuestionnaireCatalog::CLOSING_ID, $answeredIds, true);
    }
}
