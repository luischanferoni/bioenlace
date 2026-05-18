<?php

namespace common\components\Services\Turnos;

/**
 * Razones declaradas al cancelar un turno (texto orientado a usuario).
 * No reemplazan {@see \common\models\Turno::estado_motivo} de rol (paciente vs médico/efector): ese valor
 * lo fija el flujo (p. ej. autogestión paciente → siempre CANCELADO_X_PACIENTE).
 *
 * Los códigos de esta clase se envían en POST como `razon_cancelacion` y se persisten en
 * {@see \common\models\TurnoEventoAudit::registrar()} (`meta_json`), junto con la etiqueta legible.
 *
 * Futuro endpoint cancelación por médico/app staff: usar {@see self::medicoAppOpcionesSelect()} y
 * `estado_motivo` = CANCELADO_X_MEDICO en el lifecycle.
 */
final class TurnoCancelacionRazones
{
    /** @var list<string> */
    public const CODIGOS_PACIENTE_APP = [
        self::COD_PAC_ENFERMEDAD,
        self::COD_PAC_OTRO_COMPROMISO,
        self::COD_PAC_YA_MEJORE,
        self::COD_PAC_RESERVA_ERRONEA,
        self::COD_PAC_OTRO_TURNO_EN_OTRO_LUGAR,
        self::COD_PAC_TRANSPORTE,
        self::COD_PAC_LABORAL_ACADEMICO,
        self::COD_PAC_OTRO,
    ];

    /** @var list<string> */
    public const CODIGOS_MEDICO_APP = [
        self::COD_MED_PACIENTE_SOLICITA_CANCELACION,
        self::COD_MED_PACIENTE_AVISA_NO_ASISTE,
        self::COD_MED_AGENDA_EFECTOR,
        self::COD_MED_EMERGENCIA,
        self::COD_MED_RESUELTO_TELEMED,
        self::COD_MED_REASIGNACION,
        self::COD_MED_OTRO,
    ];

    /** Cancelación cerrada sin ofrecer reubicación (pedido explícito del paciente sin app). */
    public const COD_MED_PACIENTE_SOLICITA_CANCELACION = 'MED_PACIENTE_SOLICITA_CANCELACION';

    public const COD_PAC_ENFERMEDAD = 'PAC_ENFERMEDAD';
    public const COD_PAC_OTRO_COMPROMISO = 'PAC_OTRO_COMPROMISO';
    public const COD_PAC_YA_MEJORE = 'PAC_YA_MEJORE';
    public const COD_PAC_RESERVA_ERRONEA = 'PAC_RESERVA_ERRONEA';
    public const COD_PAC_OTRO_TURNO_EN_OTRO_LUGAR = 'PAC_OTRO_TURNO_EN_OTRO_LUGAR';
    public const COD_PAC_TRANSPORTE = 'PAC_TRANSPORTE';
    public const COD_PAC_LABORAL_ACADEMICO = 'PAC_LABORAL_ACADEMICO';
    public const COD_PAC_OTRO = 'PAC_OTRO';

    public const COD_MED_AGENDA_EFECTOR = 'MED_AGENDA_EFECTOR';
    public const COD_MED_EMERGENCIA = 'MED_EMERGENCIA';
    public const COD_MED_PACIENTE_AVISA_NO_ASISTE = 'MED_PACIENTE_AVISA_NO_ASISTE';
    public const COD_MED_RESUELTO_TELEMED = 'MED_RESUELTO_TELEMED';
    public const COD_MED_REASIGNACION = 'MED_REASIGNACION';
    public const COD_MED_OTRO = 'MED_OTRO';

    /** @var array<string, string> */
    private const ETIQUETAS_PACIENTE = [
        self::COD_PAC_ENFERMEDAD => 'Enfermedad o síntomas: no puedo asistir',
        self::COD_PAC_OTRO_COMPROMISO => 'Otro compromiso u obligación',
        self::COD_PAC_YA_MEJORE => 'Ya mejoré / ya no necesito esta consulta',
        self::COD_PAC_RESERVA_ERRONEA => 'Reservé el turno por error',
        self::COD_PAC_OTRO_TURNO_EN_OTRO_LUGAR => 'Conseguí otro turno (misma u otra institución)',
        self::COD_PAC_TRANSPORTE => 'Dificultades de transporte o distancia',
        self::COD_PAC_LABORAL_ACADEMICO => 'Motivos laborales o de estudio',
        self::COD_PAC_OTRO => 'Otro motivo',
    ];

    /** @var array<string, string> */
    private const ETIQUETAS_MEDICO = [
        self::COD_MED_PACIENTE_SOLICITA_CANCELACION => 'Cancelación solicitada por el paciente (sin app)',
        self::COD_MED_AGENDA_EFECTOR => 'Ajuste de agenda del consultorio',
        self::COD_MED_EMERGENCIA => 'Emergencia o fuerza mayor',
        self::COD_MED_PACIENTE_AVISA_NO_ASISTE => 'Paciente avisó que no asistirá',
        self::COD_MED_RESUELTO_TELEMED => 'Consulta resuelta por otra vía (p. ej. telemedicina)',
        self::COD_MED_REASIGNACION => 'Reasignación a otro profesional o servicio',
        self::COD_MED_OTRO => 'Otro motivo',
    ];

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function pacienteAppOpcionesSelect(): array
    {
        $out = [];
        foreach (self::CODIGOS_PACIENTE_APP as $code) {
            $out[] = ['value' => $code, 'label' => self::ETIQUETAS_PACIENTE[$code] ?? $code];
        }

        return $out;
    }

    /**
     * Para futuro POST cancelar-como-medico / flujo profesional (misma forma: campo `razon_cancelacion`).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function medicoAppOpcionesSelect(): array
    {
        $out = [];
        foreach (self::CODIGOS_MEDICO_APP as $code) {
            $out[] = ['value' => $code, 'label' => self::ETIQUETAS_MEDICO[$code] ?? $code];
        }

        return $out;
    }

    /**
     * Mapa value => label para dropdowns Yii (calendario staff).
     *
     * @return array<string, string>
     */
    public static function medicoAppOpcionesDropdown(): array
    {
        $out = [];
        foreach (self::medicoAppOpcionesSelect() as $opt) {
            $out[$opt['value']] = $opt['label'];
        }

        return $out;
    }

    public static function esCodigoPacienteAppValido(string $code): bool
    {
        return $code !== '' && in_array($code, self::CODIGOS_PACIENTE_APP, true);
    }

    public static function esCodigoMedicoAppValido(string $code): bool
    {
        return $code !== '' && in_array($code, self::CODIGOS_MEDICO_APP, true);
    }

    public static function etiquetaPacienteApp(string $code): string
    {
        return self::ETIQUETAS_PACIENTE[$code] ?? $code;
    }

    public static function etiquetaMedicoApp(string $code): string
    {
        return self::ETIQUETAS_MEDICO[$code] ?? $code;
    }

    /** Cancelación inmediata (CANCELADO); no pasa por EN_RESOLUCION. */
    public static function staffCancelacionDirecta(string $code): bool
    {
        return in_array($code, [
            self::COD_MED_PACIENTE_SOLICITA_CANCELACION,
            self::COD_MED_PACIENTE_AVISA_NO_ASISTE,
        ], true);
    }

    /**
     * Completa `options` del campo `razon_cancelacion` (paciente) en ui_json GET.
     *
     * @param array<string, mixed> $def
     * @return array<string, mixed>
     */
    public static function aplicarOpcionesRazonEnDefinicionUiJson(array $def): array
    {
        if (($def['kind'] ?? '') !== 'ui_definition' || ($def['ui_type'] ?? '') !== 'ui_json') {
            return $def;
        }
        $blocks = isset($def['blocks']) && is_array($def['blocks']) ? $def['blocks'] : [];
        foreach ($blocks as $i => $b) {
            if (!is_array($b) || ($b['kind'] ?? '') !== 'fields') {
                continue;
            }
            $fields = isset($b['fields']) && is_array($b['fields']) ? $b['fields'] : [];
            foreach ($fields as $j => $f) {
                if (!is_array($f) || ($f['name'] ?? '') !== 'razon_cancelacion') {
                    continue;
                }
                $f['options'] = self::pacienteAppOpcionesSelect();
                $fields[$j] = $f;
                $b['fields'] = $fields;
                $blocks[$i] = $b;
                $def['blocks'] = $blocks;

                return $def;
            }
        }

        return $def;
    }

    /**
     * Completa `options` del campo `razon_cancelacion` (staff / MED_*) en ui_json GET.
     *
     * @param array<string, mixed> $def
     * @return array<string, mixed>
     */
    public static function aplicarOpcionesRazonMedicoEnDefinicionUiJson(array $def): array
    {
        if (($def['kind'] ?? '') !== 'ui_definition' || ($def['ui_type'] ?? '') !== 'ui_json') {
            return $def;
        }
        $blocks = isset($def['blocks']) && is_array($def['blocks']) ? $def['blocks'] : [];
        foreach ($blocks as $i => $b) {
            if (!is_array($b) || ($b['kind'] ?? '') !== 'fields') {
                continue;
            }
            $fields = isset($b['fields']) && is_array($b['fields']) ? $b['fields'] : [];
            foreach ($fields as $j => $f) {
                if (!is_array($f) || ($f['name'] ?? '') !== 'razon_cancelacion') {
                    continue;
                }
                $f['options'] = self::medicoAppOpcionesSelect();
                $f['required'] = true;
                $fields[$j] = $f;
                $b['fields'] = $fields;
                $blocks[$i] = $b;
                $def['blocks'] = $blocks;

                return $def;
            }
        }

        return $def;
    }
}
