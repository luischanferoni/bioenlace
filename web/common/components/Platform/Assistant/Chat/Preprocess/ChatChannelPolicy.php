<?php

namespace common\components\Platform\Assistant\Chat\Preprocess;

/**
 * Política de canal del asistente (síntoma vs trámite, menú, staff data-access).
 *
 * Vive en PHP: es lógica estable del motor, no configuración de producto por intent.
 * Copy conversacional: {@see \common\components\Platform\Assistant\Chat\Channels\Conversational\ChatConversationalConfig}.
 *
 * Predicados nombrados (p. ej. `own_agenda_config_edit`) solo para cablear metadata
 * de superficies DataAccess que ya referencian un id estable.
 */
final class ChatChannelPolicy
{
    private const SYMPTOM = '/\b(problema|dolor|duele|sintoma|malestar|enfermo|fiebre|tos|nausea|vomito|mareo|hinchazon|hincho|hinchado|inflamado|presion|diabetes|hipertension|chichon|golpe|hematoma|moreton|bulto|herida|sangra|sangro|sangrado|pinchazo|punzada|ardor|arde|picazon|comezon|ahogo|asfixia|palpitacion|sudor|suda|ansioso|ansiedad|insomnio|lastim|molestia|respiro|respirar|respiracion)\b|falta.{0,24}aire|no puedo dormir|me siento mal/u';

    private const BODY_PART = '/\b(cabeza|cuello|garganta|oido|oidos|ojo|ojos|nariz|pecho|torax|corazon|pulmon|abdomen|panza|estomago|barriga|espalda|brazo|brazos|mano|manos|hombro|codo|muneca|pierna|piernas|pie|pies|rodilla|tobillo|cadera|diente|dientes|muela|piel|cuerpo)\b/u';

    private const SCHEDULING = '/\b(turno|turnos|reservar|sacar turno|cancelar turno|cancelar|agenda|cita|reprogramar|sobreturno)\b/u';

    private const STUDY_REQUEST = '/\b(ecografia|ecografía|mamografia|mamografía|radiografia|radiografía|ultrasonido|laboratorio|kinesio|kinesiologia|fisioterapia|analisis de sangre|analisis de orina|necesito una|necesito un estudio|turno para (estudio|ecografia|mamografia|radiografia|laboratorio|kinesio))\b/u';

    private const EDIT_VERB = '/\b(editar|modificar|actualizar|cambiar|corregir|ajustar|configurar)\b/u';

    private const ALTA_VERB = '/\b(crear|agregar|nuevo|nueva|alta|asociar|dar de alta)\b/u';

    private const BAJA_VERB = '/\b(quitar|sacar|desasignar|eliminar|dar de baja|baja)\b/u';

    private const AGENDA_TARGET = '/\b(agenda|horario|horarios|disponibilidad|cupo|formas?\s+de\s+atencion|forma\s+de\s+atencion|teleconsulta|intervalo|modalidad)\b/u';

    private const OWN_SCOPE = '/\b(mi|mis)\s+(agenda|horario|horarios|formas?\s+de\s+atencion)\b/u';

    private const STAFF_THIRD = '/\b(profesional|medico|doctor|personal)\b/u';

    private const AGENDA_DE_UNO = '/\b(agenda|horario|horarios)\s+de\s+(un|el|la|los|las)\b/u';

    private const CONFIG_TOPIC = '/\b(formas?\s+de\s+atencion|forma\s+de\s+atencion|teleconsulta|intervalo)\b/u';

    private const LIST_VERB = '/\b(listar|mostrar|mostrame|ver listado|nombres de|quienes|quien es|quién es|plantilla)\b/u';

    private const COUNT_VERB = '/\b(cuantos|cuantos hay|total de|conteo|cantidad de|numero de|cuenta)\b/u';

    private const HELP_MENU = '/\b(ayuda|menu|menú|que puedo hacer|qué puedo hacer|opciones|capacidades)\b/u';

    private const GREETING = '/\b(hola|buenas|buen dia|buen día|hey|ola)\b/u';

    private const CONDICION_LABORAL = '/\b(condicion laboral|condición laboral|planta permanente|contratado|monotribut)\b/u';

    public static function fold(string $message): string
    {
        $m = mb_strtolower(trim($message), 'UTF-8');
        if ($m === '') {
            return '';
        }

        return strtr($m, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    public static function isClinicalSymptomContent(string $message): bool
    {
        $f = self::fold($message);
        if ($f === '') {
            return false;
        }

        return (bool) preg_match(self::SYMPTOM, $f) || (bool) preg_match(self::BODY_PART, $f);
    }

    public static function isSchedulingRequest(string $message): bool
    {
        $f = self::fold($message);

        return $f !== '' && (bool) preg_match(self::SCHEDULING, $f);
    }

    public static function isStudyOrPracticeRequest(string $message): bool
    {
        $f = self::fold($message);

        return $f !== '' && (bool) preg_match(self::STUDY_REQUEST, $f);
    }

    /** Pedido explícito de trámite (turno/estudio) — no dejar solo en charla. */
    public static function isExplicitOperationalCareRequest(string $message): bool
    {
        return self::isSchedulingRequest($message) || self::isStudyOrPracticeRequest($message);
    }

    public static function isCapabilityMenuQuery(string $message): bool
    {
        $f = self::fold($message);

        return $f !== '' && (bool) preg_match(self::HELP_MENU, $f);
    }

    public static function isGreeting(string $message): bool
    {
        $f = self::fold($message);

        return $f !== '' && (bool) preg_match(self::GREETING, $f);
    }

    public static function suggestsStaffAgendaEdit(string $message): bool
    {
        $f = self::fold($message);
        if ($f === '') {
            return false;
        }
        if (!preg_match(self::EDIT_VERB, $f) || !preg_match(self::AGENDA_TARGET, $f)) {
            return false;
        }
        if (preg_match(self::ALTA_VERB, $f) || preg_match(self::OWN_SCOPE, $f) || preg_match(self::BAJA_VERB, $f)) {
            return false;
        }

        return (bool) preg_match(self::STAFF_THIRD, $f)
            || (bool) preg_match(self::AGENDA_DE_UNO, $f)
            || (bool) preg_match(self::CONFIG_TOPIC, $f);
    }

    public static function suggestsOwnAgendaEdit(string $message): bool
    {
        $f = self::fold($message);
        if ($f === '') {
            return false;
        }

        return (bool) preg_match(self::EDIT_VERB, $f)
            && (bool) preg_match(self::OWN_SCOPE, $f)
            && (bool) preg_match(self::AGENDA_TARGET, $f)
            && !preg_match(self::ALTA_VERB, $f)
            && !preg_match(self::BAJA_VERB, $f);
    }

    public static function isStaffDataAccessEditQuery(string $message): bool
    {
        $f = self::fold($message);
        if ($f === '') {
            return false;
        }

        return (bool) preg_match(self::EDIT_VERB, $f) && !preg_match(self::SCHEDULING, $f);
    }

    public static function isStaffDataAccessQuery(string $message): bool
    {
        $f = self::fold($message);
        if ($f === '') {
            return false;
        }

        return (bool) preg_match(self::LIST_VERB, $f)
            || (bool) preg_match(self::COUNT_VERB, $f)
            || str_contains($f, 'profesionales del centro')
            || str_contains($f, 'medicos del centro')
            || str_contains($f, 'resumen');
    }

    public static function isStaffDataAccessOperationalQuery(string $message): bool
    {
        return self::isStaffDataAccessQuery($message)
            || self::isStaffDataAccessEditQuery($message)
            || self::suggestsOwnAgendaEdit($message)
            || self::suggestsStaffAgendaEdit($message)
            || self::looksLikeOwnCoberturaOrPlantel($message)
            || self::looksLikeStaffPlantel($message);
    }

    public static function looksLikeConditionLaboral(string $message): bool
    {
        $f = self::fold($message);

        return $f !== '' && (bool) preg_match(self::CONDICION_LABORAL, $f);
    }

    /**
     * Predicado nombrado para metadata (DataAccess subject_resolver, etc.).
     */
    public static function namedPredicate(string $predicateId, string $message): bool
    {
        $id = trim($predicateId);
        if ($id === '') {
            return false;
        }

        return match ($id) {
            'clinical_symptom' => self::isClinicalSymptomContent($message),
            'own_agenda_config_edit' => self::suggestsOwnAgendaEdit($message),
            'staff_agenda_config_edit' => self::suggestsStaffAgendaEdit($message),
            'scheduling_operational' => self::isSchedulingRequest($message),
            'paciente_reservar_turno', 'paciente_pedido_estudio' => self::isExplicitOperationalCareRequest($message),
            'help_menu_query' => self::isCapabilityMenuQuery($message),
            'greeting' => self::isGreeting($message),
            default => false,
        };
    }

    public static function lastLineMatchingClinicalSymptom(string $multilineText): string
    {
        $lines = preg_split('/\R/u', $multilineText) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim((string) $lines[$i]);
            if ($line !== '' && self::isClinicalSymptomContent($line)) {
                return $line;
            }
        }

        return '';
    }

    /**
     * Ofrecer botón Solicitar Atención tras síntoma (mensaje actual o historial del paciente).
     */
    public static function shouldOfferBookingButton(string $content, string $patientHistory = ''): bool
    {
        if (self::isClinicalSymptomContent($content)) {
            return true;
        }

        return self::lastLineMatchingClinicalSymptom($patientHistory) !== '';
    }

    /**
     * Ajusta el user_goal del preprocess (IA o heurística).
     */
    public static function resolveUserGoal(
        string $normalized,
        string $goal,
        ?string $alsoMatchText = null,
        string $historyText = ''
    ): string {
        $texts = self::matchTexts($normalized, $alsoMatchText);

        if (self::anyText(static fn (string $t) => self::isClinicalSymptomContent($t), $texts)
            && !self::anyText(static fn (string $t) => self::isExplicitOperationalCareRequest($t), $texts)
        ) {
            if (in_array($goal, ['informational', 'unclear', 'operational', 'in_flow_question'], true)) {
                return 'conversational';
            }
        }

        if ($historyText !== ''
            && self::isClinicalSymptomContent($historyText)
            && !self::anyText(static fn (string $t) => self::isExplicitOperationalCareRequest($t), $texts)
            && in_array($goal, ['informational', 'unclear', 'operational', 'in_flow_question'], true)
        ) {
            return 'conversational';
        }

        if (self::anyText(static fn (string $t) => self::isStaffDataAccessOperationalQuery($t), $texts)
            || self::anyText(static fn (string $t) => self::isExplicitOperationalCareRequest($t), $texts)
        ) {
            return 'operational';
        }

        return $goal;
    }

    public static function heuristicUserGoal(string $content): string
    {
        if (self::isExplicitOperationalCareRequest($content) || self::isStaffDataAccessOperationalQuery($content)) {
            return 'operational';
        }
        if (self::isCapabilityMenuQuery($content)) {
            return 'informational';
        }
        if (self::isGreeting($content) || self::isClinicalSymptomContent($content)) {
            return 'conversational';
        }

        return 'unclear';
    }

    private static function looksLikeOwnCoberturaOrPlantel(string $message): bool
    {
        $f = self::fold($message);
        if ($f === '') {
            return false;
        }
        if (preg_match(self::STAFF_THIRD, $f)) {
            return false;
        }

        return str_contains($f, 'mi cobertura')
            || str_contains($f, 'cargar mi cobertura')
            || str_contains($f, 'mis horarios')
            || str_contains($f, 'plantel de guardia')
            || str_contains($f, 'horario de guardia');
    }

    private static function looksLikeStaffPlantel(string $message): bool
    {
        $f = self::fold($message);
        if ($f === '') {
            return false;
        }

        return str_contains($f, 'asignar guardia')
            || str_contains($f, 'plantel guardia')
            || str_contains($f, 'horarios de plantel')
            || str_contains($f, 'cobertura de un profesional')
            || str_contains($f, 'horarios de guardia de un');
    }

    /**
     * @return list<string>
     */
    private static function matchTexts(string $normalized, ?string $also): array
    {
        $out = [];
        $n = trim($normalized);
        if ($n !== '') {
            $out[] = $n;
        }
        $o = $also !== null ? trim($also) : '';
        if ($o !== '' && ($out === [] || $o !== $out[0])) {
            $out[] = $o;
        }

        return $out;
    }

    /**
     * @param callable(string): bool $fn
     * @param list<string> $texts
     */
    private static function anyText(callable $fn, array $texts): bool
    {
        foreach ($texts as $t) {
            if ($fn($t)) {
                return true;
            }
        }

        return false;
    }
}
