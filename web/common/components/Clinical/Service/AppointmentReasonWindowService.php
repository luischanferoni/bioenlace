<?php

namespace common\components\Clinical\Service;

use common\models\Clinical\Encounter;
use common\models\Turno;
use Yii;

/**
 * Ventana de captura de motivos de consulta (app paciente): abierta hasta N minutos antes del turno.
 */
final class AppointmentReasonWindowService
{
    public const DEFAULT_CLOSE_MINUTES_BEFORE = 2;
    public const DEFAULT_MEDICO_HC_OPEN_MINUTES_BEFORE = 1;

    public static function minutesBeforeClose(): int
    {
        $v = (int) (Yii::$app->params['motivos_consulta_cierre_minutos'] ?? self::DEFAULT_CLOSE_MINUTES_BEFORE);

        return max(0, $v);
    }

    /** Minutos antes del turno en que el médico puede abrir historia clínica / motivos. */
    public static function minutesBeforeMedicoHistoriaClinica(): int
    {
        $v = (int) (
            Yii::$app->params['historia_clinica_apertura_medico_minutos']
            ?? self::DEFAULT_MEDICO_HC_OPEN_MINUTES_BEFORE
        );

        return max(0, $v);
    }

    public static function findEncounter(int $encounterId): ?Encounter
    {
        return Encounter::findOne(['id' => $encounterId]);
    }

    /**
     * Timestamp Unix del inicio del turno vinculado (zona producto), o null si no hay turno/fecha/hora válida.
     */
    public static function turnoStartsAt(Encounter $encounter): ?int
    {
        $turno = self::resolveTurno($encounter);
        if ($turno === null || empty($turno->fecha)) {
            return null;
        }

        $horaNorm = self::normalizeHoraParaInicio($turno->hora);
        if ($horaNorm === null) {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $turno->fecha . ' ' . $horaNorm,
            self::productTimezone()
        );

        return $dt !== false ? $dt->getTimestamp() : null;
    }

    public static function isInputOpen(int $encounterId): bool
    {
        $encounter = self::findEncounter($encounterId);
        if ($encounter === null) {
            return false;
        }

        return self::isInputOpenForEncounter($encounter);
    }

    public static function isInputOpenForEncounter(Encounter $encounter): bool
    {
        $turnoAt = self::turnoStartsAt($encounter);
        if ($turnoAt === null) {
            return false;
        }

        $closeAt = $turnoAt - self::minutesBeforeClose() * 60;

        return self::nowTimestamp() < $closeAt;
    }

    /**
     * Historia clínica / motivos para el médico: desde N minutos antes del turno (turno ambulatorio).
     * Sin turno vinculado (guardia, etc.) → visible.
     */
    public static function isHistoriaClinicaVisibleForEncounter(Encounter $encounter): bool
    {
        $turnoAt = self::turnoStartsAt($encounter);
        if ($turnoAt === null) {
            return true;
        }

        $openAt = $turnoAt - self::minutesBeforeMedicoHistoriaClinica() * 60;

        return self::nowTimestamp() >= $openAt;
    }

    public static function medicoHistoriaClinicaOpensAt(Encounter $encounter): ?int
    {
        $turnoAt = self::turnoStartsAt($encounter);
        if ($turnoAt === null) {
            return null;
        }

        return $turnoAt - self::minutesBeforeMedicoHistoriaClinica() * 60;
    }

    /**
     * @return array{
     *   visible: bool,
     *   disponible_desde: string|null,
     *   turno_en: string|null,
     *   minutos_antes_apertura: int,
     *   minutos_antes_cierre_paciente: int
     * }
     */
    public static function apiHistoriaClinicaGateState(Encounter $encounter): array
    {
        $turnoAt = self::turnoStartsAt($encounter);
        $openAt = self::medicoHistoriaClinicaOpensAt($encounter);

        return [
            'visible' => self::isHistoriaClinicaVisibleForEncounter($encounter),
            'disponible_desde' => $openAt !== null ? date('c', $openAt) : null,
            'turno_en' => $turnoAt !== null ? date('c', $turnoAt) : null,
            'minutos_antes_apertura' => self::minutesBeforeMedicoHistoriaClinica(),
            'minutos_antes_cierre_paciente' => self::minutesBeforeClose(),
        ];
    }

    /**
     * @return array{
     *   input_abierto: bool,
     *   cierre_en: string|null,
     *   turno_en: string|null,
     *   minutos_antes_cierre: int,
     *   motivos_ia_processed_at: string|null,
     *   motivos_resumen: string|null
     * }
     */
    public static function apiState(int $encounterId): array
    {
        $encounter = self::findEncounter($encounterId);
        if ($encounter === null) {
            return [
                'input_abierto' => false,
                'cierre_en' => null,
                'turno_en' => null,
                'minutos_antes_cierre' => self::minutesBeforeClose(),
                'motivos_ia_processed_at' => null,
                'motivos_resumen' => null,
            ];
        }

        $turnoAt = self::turnoStartsAt($encounter);
        $minutes = self::minutesBeforeClose();
        $closeAt = $turnoAt !== null ? $turnoAt - $minutes * 60 : null;

        $reason = trim((string) $encounter->reason_text);

        return [
            'input_abierto' => self::isInputOpenForEncounter($encounter),
            'cierre_en' => $closeAt !== null ? date('c', $closeAt) : null,
            'turno_en' => $turnoAt !== null ? date('c', $turnoAt) : null,
            'minutos_antes_cierre' => $minutes,
            'motivos_ia_processed_at' => $encounter->motivos_ia_processed_at
                ? date('c', strtotime((string) $encounter->motivos_ia_processed_at))
                : null,
            'motivos_resumen' => $reason !== '' ? $reason : null,
        ];
    }

    /**
     * @throws \yii\web\ForbiddenHttpException
     */
    public static function assertInputOpen(int $encounterId): void
    {
        if (!self::isInputOpen($encounterId)) {
            throw new \yii\web\ForbiddenHttpException(
                'El plazo para cargar motivos de consulta finalizó. Se cierra '
                . self::minutesBeforeClose()
                . ' minuto(s) antes del turno; el médico verá el resumen al iniciar la atención.'
            );
        }
    }

    private static function nowTimestamp(): int
    {
        return (new \DateTimeImmutable('now', self::productTimezone()))->getTimestamp();
    }

    private static function productTimezone(): \DateTimeZone
    {
        try {
            return new \DateTimeZone(Yii::$app->timeZone ?: 'America/Argentina/Tucuman');
        } catch (\Exception $e) {
            return new \DateTimeZone('America/Argentina/Tucuman');
        }
    }

    /**
     * HH:mm:ss para combinar con fecha (acepta HH:mm o HH:mm:ss en BD).
     */
    private static function normalizeHoraParaInicio(?string $hora): ?string
    {
        if ($hora === null || trim($hora) === '') {
            return null;
        }
        $t = trim($hora);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $t, $m) !== 1) {
            return null;
        }
        $ss = isset($m[3]) ? (int) $m[3] : 0;

        return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], $ss);
    }

    private static function resolveTurno(Encounter $encounter): ?Turno
    {
        if ($encounter->appointment_id) {
            return Turno::findActive()->andWhere(['id_turnos' => (int) $encounter->appointment_id])->one();
        }

        if ($encounter->parent_type === Encounter::PARENT_TURNO && $encounter->parent_id) {
            return Turno::findActive()->andWhere(['id_turnos' => (int) $encounter->parent_id])->one();
        }

        return null;
    }
}
