<?php

namespace common\components\Platform\Assistant\Chat\Channels\Conversational;

use common\models\AsistenteConversacion;
use common\models\AsistenteInteraccion;
use Yii;

/**
 * Ventana acotada del historial del chat para el canal conversacional.
 *
 * Evita prompts gigantes: tope de turnos, tope de caracteres y corte al encontrar
 * un salto operativo ([action_id:…]).
 */
final class ConversationalHistoryWindow
{
    private const BOT_SENDER = 'BOT';
    private const DEFAULT_MAX_TURNOS = 5;
    private const DEFAULT_MAX_CHARS = 3200;

    /**
     * Texto multilínea "Paciente: … / Asistente: …" o vacío si no hay historial útil.
     */
    public static function formatForPrompt(int $userId, string $currentContent): string
    {
        $uidStr = (string) $userId;
        $conversacion = AsistenteConversacion::findOne([
            'usuario_id' => $uidStr,
            'bot_id' => self::BOT_SENDER,
        ]);
        if ($conversacion === null) {
            return '';
        }

        $maxTurnos = max(1, (int) (Yii::$app->params['asistente_conversacional_historial_max_turnos'] ?? self::DEFAULT_MAX_TURNOS));
        $maxChars = max(200, (int) (Yii::$app->params['asistente_conversacional_historial_max_chars'] ?? self::DEFAULT_MAX_CHARS));

        $fetchLimit = min(80, $maxTurnos * 4 + 8);
        $rows = AsistenteInteraccion::find()
            ->where(['conversacion_id' => (int) $conversacion->id])
            ->orderBy(['id' => SORT_DESC])
            ->limit($fetchLimit)
            ->all();

        return self::buildFromInteractions($rows, $uidStr, $currentContent, $maxTurnos, $maxChars);
    }

    /**
     * @param AsistenteInteraccion[] $rowsNewestFirst
     */
    public static function buildFromInteractions(
        array $rowsNewestFirst,
        string $userId,
        string $currentContent,
        int $maxTurnos,
        int $maxChars
    ): string {
        $currentTrimmed = trim($currentContent);
        $lines = [];
        $skippedCurrentDuplicate = false;

        foreach ($rowsNewestFirst as $row) {
            if (!$row instanceof AsistenteInteraccion) {
                continue;
            }

            $text = trim((string) $row->texto);
            if ($text === '') {
                continue;
            }

            if (self::isOperationalBoundary($text)) {
                break;
            }

            if (!self::isEligibleLine($text)) {
                continue;
            }

            if (
                !$skippedCurrentDuplicate
                && (string) $row->sender_id === $userId
                && $text === $currentTrimmed
            ) {
                $skippedCurrentDuplicate = true;
                continue;
            }

            $role = (string) $row->sender_id === self::BOT_SENDER ? 'Asistente' : 'Paciente';
            array_unshift($lines, $role . ': ' . $text);
        }

        $lines = self::trimToBudget($lines, $maxTurnos, $maxChars);

        return $lines === [] ? '' : implode("\n", $lines);
    }

    public static function isEligibleLine(string $text): bool
    {
        if ($text === 'Consulta procesada') {
            return false;
        }

        return !self::isOperationalBoundary($text);
    }

    /**
     * @param list<string> $lines oldest-first
     * @return list<string>
     */
    public static function trimToBudget(array $lines, int $maxTurnos, int $maxChars): array
    {
        if ($lines === []) {
            return [];
        }

        $maxLines = max(2, $maxTurnos * 2);

        while (count($lines) > $maxLines) {
            array_shift($lines);
        }

        while ($lines !== [] && strlen(implode("\n", $lines)) > $maxChars) {
            array_shift($lines);
        }

        return $lines;
    }

    private static function isOperationalBoundary(string $text): bool
    {
        return strncmp($text, '[action_id:', 11) === 0;
    }
}
