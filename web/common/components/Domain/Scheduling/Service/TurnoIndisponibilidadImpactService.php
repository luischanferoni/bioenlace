<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\models\Scheduling\Turno;
use common\models\TurnoResolucion;

/**
 * Turnos pendientes afectados por indisponibilidad de un PES en un rango de fechas
 * (licencia, bloqueo de agenda, etc.).
 */
final class TurnoIndisponibilidadImpactService
{
    /**
     * @return array{
     *   fecha_inicio: string,
     *   fecha_fin: string|null,
     *   turnos_afectados_total: int,
     *   turnos: list<array{id_turno: int, fecha: string, hora: string, paciente: string}>,
     *   requiere_confirmacion: bool,
     *   mensaje: string
     * }
     */
    public static function previewPorPesYRango(int $idPes, string $fechaInicio, ?string $fechaFin): array
    {
        $fi = self::normalizeYmd($fechaInicio);
        if ($fi === '') {
            throw new \InvalidArgumentException('fecha_inicio es obligatoria.');
        }
        $ff = self::normalizeYmd($fechaFin);

        $turnos = self::findTurnosAfectados($idPes, $fi, $ff !== '' ? $ff : null);
        $items = [];
        foreach (array_slice($turnos, 0, 15) as $turno) {
            $items[] = self::turnoToPreviewRow($turno);
        }

        $total = count($turnos);
        $mensaje = self::buildMensajePreview($total, $fi, $ff !== '' ? $ff : null);

        return [
            'fecha_inicio' => $fi,
            'fecha_fin' => $ff !== '' ? $ff : null,
            'turnos_afectados_total' => $total,
            'turnos' => $items,
            'requiere_confirmacion' => $total > 0,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * Marca turnos pendientes del rango en resolución (origen licencia) y notifica pacientes.
     *
     * @return int cantidad de turnos marcados
     */
    public static function aplicarPorLicencia(int $idPes, string $fechaInicio, ?string $fechaFin, array $meta = []): int
    {
        $fi = self::normalizeYmd($fechaInicio);
        if ($fi === '') {
            throw new \InvalidArgumentException('fecha_inicio es obligatoria.');
        }
        $ff = self::normalizeYmd($fechaFin);

        $turnos = self::findTurnosAfectados($idPes, $fi, $ff !== '' ? $ff : null);
        if ($turnos === []) {
            return 0;
        }

        TurnoResolucionService::crearDesdeLicencia($turnos, array_merge($meta, [
            'fecha_inicio' => $fi,
            'fecha_fin' => $ff !== '' ? $ff : null,
            'id_profesional_efector_servicio' => $idPes,
        ]));

        return count($turnos);
    }

    /**
     * Marca turnos pendientes (desde hoy) en resolución por baja de PES y notifica pacientes.
     *
     * @return int cantidad de turnos marcados
     */
    public static function aplicarPorBajaPes(int $idPes, array $meta = []): int
    {
        if ($idPes <= 0) {
            return 0;
        }
        $hoy = date('Y-m-d');
        $turnos = self::findTurnosAfectados($idPes, $hoy, null, [Turno::ESTADO_PENDIENTE]);
        if ($turnos === []) {
            return 0;
        }

        TurnoResolucionService::crearDesdeBajaPes($turnos, array_merge($meta, [
            'id_profesional_efector_servicio' => $idPes,
        ]));

        return count($turnos);
    }

    /**
     * @param list<string> $estados
     * @return Turno[]
     */
    public static function findTurnosAfectados(
        int $idPes,
        string $fechaInicio,
        ?string $fechaFin,
        array $estados = [Turno::ESTADO_PENDIENTE]
    ): array {
        if ($idPes <= 0) {
            return [];
        }
        $fi = self::normalizeYmd($fechaInicio);
        if ($fi === '') {
            return [];
        }
        $ff = $fechaFin !== null ? self::normalizeYmd($fechaFin) : '';
        if ($estados === []) {
            $estados = [Turno::ESTADO_PENDIENTE];
        }

        $query = Turno::find()
            ->where([
                'id_profesional_efector_servicio' => $idPes,
                'estado' => $estados,
            ])
            ->andWhere(['>=', 'fecha', $fi]);
        if ($ff !== '') {
            $query->andWhere(['<=', 'fecha', $ff]);
        }

        /** @var Turno[] $rows */
        $rows = $query
            ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC])
            ->all();

        return $rows;
    }

    /**
     * @return array{id_turno: int, fecha: string, hora: string, paciente: string}
     */
    private static function turnoToPreviewRow(Turno $turno): array
    {
        $paciente = '';
        $persona = $turno->persona;
        if ($persona !== null) {
            $paciente = trim(trim((string) $persona->apellido) . ', ' . trim((string) $persona->nombre));
        }

        return [
            'id_turno' => (int) $turno->id_turnos,
            'fecha' => (string) $turno->fecha,
            'hora' => substr((string) $turno->hora, 0, 5),
            'paciente' => $paciente,
        ];
    }

    private static function buildMensajePreview(int $total, string $fechaInicio, ?string $fechaFin): string
    {
        if ($total <= 0) {
            return 'No hay turnos pendientes en el período de la licencia.';
        }
        $rango = self::formatRangoPhrase($fechaInicio, $fechaFin);
        $sufijo = $total === 1 ? 'turno pendiente' : 'turnos pendientes';

        return $total . ' ' . $sufijo . ' en el período' . ($rango !== '' ? ' ' . $rango : '')
            . '. Los pacientes podrán reubicar o cancelar desde la app.';
    }

    private static function formatRangoPhrase(string $fechaInicio, ?string $fechaFin): string
    {
        $fi = self::formatFechaEs($fechaInicio);
        $ff = $fechaFin !== null && $fechaFin !== '' ? self::formatFechaEs($fechaFin) : '';
        if ($fi !== '' && $ff !== '') {
            return 'del ' . $fi . ' al ' . $ff;
        }
        if ($fi !== '') {
            return 'desde el ' . $fi;
        }

        return '';
    }

    private static function formatFechaEs(string $ymd): string
    {
        try {
            return (new \DateTimeImmutable($ymd))->format('d/m/Y');
        } catch (\Throwable $e) {
            return $ymd;
        }
    }

    private static function normalizeYmd(?string $iso): string
    {
        $s = trim((string) $iso);
        if ($s === '') {
            return '';
        }
        try {
            return (new \DateTimeImmutable($s))->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
