<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Scheduling\Agenda\Application\Service\ReservaTurnoTriageCatalogService;
use common\components\Domain\Scheduling\Agenda\Application\Service\TurnoReservaTriageDraftService;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use common\models\Platform\AsistenteConversacion;
use common\models\Platform\AsistenteInteraccion;
use common\models\Scheduling\Turno;

/**
 * Lo que el paciente ya contó antes de tener el turno: trayectoria del triage de reserva y
 * mensajes al asistente anteriores al alta del turno.
 *
 * Es el insumo con el que se deduce el motivo cuando no hay (o además de) mensajes del chat.
 */
final class AppointmentReasonInitialContextService
{
    /** Ventana hacia atrás desde el alta del turno para tomar la conversación del asistente. */
    public const ASSISTANT_LOOKBACK_DAYS = 14;

    /** Tope de mensajes del paciente que se incorporan al resumen. */
    public const ASSISTANT_MAX_MESSAGES = 30;

    /** Recorte por mensaje para no inflar el prompt con pegados largos. */
    public const ASSISTANT_MAX_MESSAGE_LENGTH = 400;

    /**
     * @return array{
     *   triage_fields: array<string, string>,
     *   triage_lines: list<string>,
     *   assistant_lines: list<string>,
     *   narrative: string,
     *   sources: list<string>
     * }
     */
    public function build(Encounter $encounter): array
    {
        $turno = $this->resolveTurno($encounter);
        $triageFields = $turno !== null
            ? (new TurnoReservaTriageDraftService())->buildFromTurno($turno)
            : [];
        $triageLines = $this->triageLines($turno, $triageFields);
        $assistantLines = $this->assistantLines($encounter, $turno);

        $blocks = [];
        $sources = [];
        if ($triageLines !== []) {
            $blocks[] = "Antecedentes de la reserva del turno:\n" . implode("\n", $triageLines);
            $sources[] = 'triage';
        }
        if ($assistantLines !== []) {
            $blocks[] = "Mensajes del paciente al asistente antes de reservar:\n" . implode("\n", $assistantLines);
            $sources[] = 'asistente';
        }

        return [
            'triage_fields' => $triageFields,
            'triage_lines' => $triageLines,
            'assistant_lines' => $assistantLines,
            'narrative' => implode("\n\n", $blocks),
            'sources' => $sources,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function triageFields(Encounter $encounter): array
    {
        $turno = $this->resolveTurno($encounter);
        if ($turno === null) {
            return [];
        }

        $fields = (new TurnoReservaTriageDraftService())->buildFromTurno($turno);
        $out = [];
        foreach ($fields as $key => $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                $out[(string) $key] = $text;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $triageFields
     * @return list<string>
     */
    private function triageLines(?Turno $turno, array $triageFields): array
    {
        if ($turno === null) {
            return [];
        }

        $catalog = new ReservaTurnoTriageCatalogService();
        $lines = [];
        $ordered = ['triage_raiz', 'triage_alarmas', 'triage_zona', 'triage_detalle', 'triage_evolucion'];
        foreach ($ordered as $field) {
            $code = trim((string) ($triageFields[$field] ?? ''));
            if ($code === '') {
                continue;
            }
            $node = $catalog->findNode($code);
            $label = $node !== null ? trim((string) ($node['label'] ?? '')) : '';
            $step = $node !== null ? trim((string) ($node['step'] ?? '')) : '';
            $stepDef = $step !== '' ? $catalog->getStep($step) : null;
            $title = $stepDef !== null ? trim((string) ($stepDef['title'] ?? '')) : '';
            $value = $label !== '' ? $label : $code;
            $lines[] = $title !== ''
                ? '- ' . $title . ' → ' . $value
                : '- ' . $field . ' → ' . $value;
        }

        $nota = $this->triageFreeNote($turno);
        if ($nota !== '') {
            $lines[] = '- Nota del paciente al reservar: ' . $nota;
        }

        return $lines;
    }

    private function triageFreeNote(Turno $turno): string
    {
        $raw = $turno->reserva_triage_meta_json ?? null;
        if ($raw === null || $raw === '') {
            return '';
        }
        $meta = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($meta) || !is_array($meta['path'] ?? null)) {
            return '';
        }
        $notas = [];
        foreach ($meta['path'] as $step) {
            if (!is_array($step)) {
                continue;
            }
            if (trim((string) ($step['field'] ?? '')) !== 'triage_nota') {
                continue;
            }
            $label = trim((string) ($step['label'] ?? ''));
            if ($label !== '') {
                $notas[] = $label;
            }
        }

        return implode(' ', $notas);
    }

    /**
     * @return list<string>
     */
    private function assistantLines(Encounter $encounter, ?Turno $turno): array
    {
        $userIds = $this->assistantUserIds((int) $encounter->subject_persona_id);
        if ($userIds === []) {
            return [];
        }

        $conversacionIds = AsistenteConversacion::find()
            ->select('id')
            ->where(['usuario_id' => $userIds, 'bot_id' => 'BOT'])
            ->column();
        if ($conversacionIds === []) {
            return [];
        }

        $query = AsistenteInteraccion::find()
            ->where(['conversacion_id' => array_map('intval', $conversacionIds)])
            ->andWhere(['<>', 'sender_id', 'BOT'])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(self::ASSISTANT_MAX_MESSAGES);

        $anchor = $this->turnoCreatedAt($turno);
        if ($anchor !== null) {
            $query->andWhere(['<=', 'created_at', date('Y-m-d H:i:s', $anchor)]);
            $query->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', $anchor - self::ASSISTANT_LOOKBACK_DAYS * 86400)]);
        }

        $lines = [];
        foreach (array_reverse($query->all()) as $interaccion) {
            $text = $this->sanitizeAssistantMessage((string) $interaccion->texto);
            if ($text !== '') {
                $lines[] = '- ' . $text;
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function assistantUserIds(int $personaId): array
    {
        if ($personaId <= 0) {
            return [];
        }
        $persona = Persona::findOne(['id_persona' => $personaId]);
        if ($persona === null || (int) $persona->id_user <= 0) {
            return [];
        }

        return [(string) (int) $persona->id_user];
    }

    private function sanitizeAssistantMessage(string $texto): string
    {
        $text = trim($texto);
        if ($text === '') {
            return '';
        }
        $text = preg_replace('/\[action_id:[^\]]*\]/', ' ', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '' || $text[0] === '{' || $text[0] === '[') {
            return '';
        }
        if (mb_strlen($text) > self::ASSISTANT_MAX_MESSAGE_LENGTH) {
            $text = mb_substr($text, 0, self::ASSISTANT_MAX_MESSAGE_LENGTH) . '…';
        }

        return $text;
    }

    private function turnoCreatedAt(?Turno $turno): ?int
    {
        if ($turno === null) {
            return null;
        }
        $raw = trim((string) ($turno->created_at ?? ''));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);

        return $ts !== false && $ts > 0 ? $ts : null;
    }

    private function resolveTurno(Encounter $encounter): ?Turno
    {
        if ($encounter->appointment_id) {
            $turno = Turno::findActive()->andWhere(['id_turnos' => (int) $encounter->appointment_id])->one();
            if ($turno instanceof Turno) {
                return $turno;
            }
        }
        if ($encounter->parent_type === Encounter::PARENT_TURNO && $encounter->parent_id) {
            $turno = Turno::findActive()->andWhere(['id_turnos' => (int) $encounter->parent_id])->one();
            if ($turno instanceof Turno) {
                return $turno;
            }
        }

        return null;
    }
}
