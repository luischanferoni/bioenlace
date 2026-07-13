<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

use common\components\Domain\Scheduling\Service\TurnoIndisponibilidadImpactService;
use common\models\ProfesionalEfectorServicio as ProfesionalEfectorServicioModel;
use common\models\Turno;
use Yii;

/**
 * Baja (soft-delete) de una asignación persona–efector–servicio.
 *
 * Sin HttpException: errores de negocio como \InvalidArgumentException.
 */
final class ProfesionalEfectorServicioBajaService
{
    /**
     * Preview de impacto en turnos al dar de baja el PES (sin persistir).
     *
     * @param array<string, mixed> $params
     * @return array{
     *   id_profesional_efector_servicio: int,
     *   id_servicio: int,
     *   turnos_pendientes_futuros: int,
     *   turnos_en_resolucion_futuros: int,
     *   afecta_turnos: bool,
     *   requiere_confirmacion: bool,
     *   preview_message: string,
     *   mensaje: string
     * }
     */
    public static function previewImpacto(int $idEfector, array $params): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Se requiere efector en sesión.');
        }

        $pes = self::resolvePesActivo($idEfector, $params);
        $idPes = (int) $pes->id;
        $idServicio = (int) $pes->id_servicio;
        $counts = self::contarTurnosFuturosPorPes($idPes);
        $pendientes = $counts['pendientes'];
        $enResolucion = $counts['en_resolucion'];
        $afecta = $pendientes > 0;
        $servicioNombre = $pes->servicio !== null
            ? (string) $pes->servicio->nombre
            : ('servicio #' . $idServicio);

        if ($pendientes > 0) {
            $msg = $pendientes === 1
                ? "Esta baja afecta 1 turno pendiente a futuro en «{$servicioNombre}». "
                    . 'Al confirmar, ese turno pasará a resolución y se notificará al paciente '
                    . 'para que reubique o cancele.'
                : "Esta baja afecta {$pendientes} turnos pendientes a futuro en «{$servicioNombre}». "
                    . 'Al confirmar, esos turnos pasarán a resolución y se notificará a los pacientes '
                    . 'para que reubiquen o cancelen.';
            if ($enResolucion > 0) {
                $msg .= $enResolucion === 1
                    ? ' Además hay 1 turno ya en resolución.'
                    : " Además hay {$enResolucion} turnos ya en resolución.";
            }
        } else {
            $msg = 'Esta baja no afecta turnos a futuro: no hay turnos pendientes '
                . "en «{$servicioNombre}» para esta asignación.";
            if ($enResolucion > 0) {
                $msg .= $enResolucion === 1
                    ? ' Hay 1 turno en resolución que no se modifica.'
                    : " Hay {$enResolucion} turnos en resolución que no se modifican.";
            }
        }

        return [
            'id_profesional_efector_servicio' => $idPes,
            'id_servicio' => $idServicio,
            'turnos_pendientes_futuros' => $pendientes,
            'turnos_en_resolucion_futuros' => $enResolucion,
            'afecta_turnos' => $afecta,
            'requiere_confirmacion' => $afecta,
            'preview_message' => $msg,
            'mensaje' => $msg,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{
     *   id_profesional_efector_servicio: int,
     *   id_persona: int,
     *   id_servicio: int,
     *   id_efector: int,
     *   turnos_reorganizados: int,
     *   message: string
     * }
     */
    public static function bajaDesdeParams(int $idEfector, array $params): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Se requiere efector en sesión.');
        }

        $pes = self::resolvePesActivo($idEfector, $params);
        $idPes = (int) $pes->id;
        $idPersona = (int) $pes->id_persona;
        $idServicio = (int) $pes->id_servicio;
        $pendientes = self::contarTurnosFuturosPorPes($idPes)['pendientes'];

        if ($pendientes > 0 && !self::ackImpactoOk($params)) {
            throw new \InvalidArgumentException(
                'Confirmá que entendiste el impacto en turnos (Entendí el impacto) antes de continuar.'
            );
        }

        $servicioNombre = $pes->servicio !== null
            ? (string) $pes->servicio->nombre
            : ('servicio #' . $idServicio);

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        $marcados = 0;
        try {
            if ($pendientes > 0) {
                $marcados = TurnoIndisponibilidadImpactService::aplicarPorBajaPes($idPes, [
                    'id_servicio' => $idServicio,
                ]);
            }

            /** @var ProfesionalEfectorServicioModel|null $fresh */
            $fresh = ProfesionalEfectorServicioModel::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($fresh === null) {
                throw new \InvalidArgumentException('La asignación ya no está activa.');
            }
            if (!$fresh->softDelete()) {
                throw new \RuntimeException(
                    'No se pudo dar de baja la asignación: ' . json_encode($fresh->getErrors())
                );
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $message = 'Se quitó la asignación del profesional al servicio «' . $servicioNombre . '».';
        if ($marcados > 0) {
            $message .= $marcados === 1
                ? ' 1 turno pasó a resolución; el paciente fue notificado.'
                : " {$marcados} turnos pasaron a resolución; los pacientes fueron notificados.";
        }

        return [
            'id_profesional_efector_servicio' => $idPes,
            'id_persona' => $idPersona,
            'id_servicio' => $idServicio,
            'id_efector' => $idEfector,
            'turnos_reorganizados' => $marcados,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function ackImpactoOk(array $params): bool
    {
        $ack = strtolower(trim((string) ($params['impacto_baja_revisado'] ?? '')));

        return $ack === '1' || $ack === 'si' || $ack === 'sí' || $ack === 'true';
    }

    /**
     * @return array{pendientes: int, en_resolucion: int}
     */
    public static function contarTurnosFuturosPorPes(int $idPes): array
    {
        if ($idPes <= 0) {
            return ['pendientes' => 0, 'en_resolucion' => 0];
        }

        $base = Turno::findActive()
            ->andWhere(['id_profesional_efector_servicio' => $idPes])
            ->andWhere(['>=', 'fecha', date('Y-m-d')])
            ->andWhere(['is', 'atendido', null]);

        $pendientes = (int) (clone $base)
            ->andWhere(['estado' => Turno::ESTADO_PENDIENTE])
            ->count();
        $enResolucion = (int) (clone $base)
            ->andWhere(['estado' => Turno::ESTADO_EN_RESOLUCION])
            ->count();

        return [
            'pendientes' => $pendientes,
            'en_resolucion' => $enResolucion,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function resolvePesActivo(int $idEfector, array $params): ProfesionalEfectorServicioModel
    {
        $idPesPost = (int) ($params['id_profesional_efector_servicio'] ?? 0);
        $idServicio = (int) ($params['id_servicio'] ?? 0);

        if ($idPesPost > 0 && $idServicio > 0) {
            $ancla = ProfesionalEfectorServicioModel::findOne(['id' => $idPesPost, 'deleted_at' => null]);
            if ($ancla === null || (int) $ancla->id_efector !== $idEfector) {
                throw new \InvalidArgumentException('Asignación inválida para este efector.');
            }
            if ((int) $ancla->id_servicio === $idServicio) {
                return $ancla;
            }
            $pes = ProfesionalEfectorServicioModel::findOneActivoPorPersonaEfectorServicio(
                (int) $ancla->id_persona,
                $idEfector,
                $idServicio
            );
            if ($pes === null) {
                throw new \InvalidArgumentException(
                    'El profesional no tiene asignación activa en ese servicio del efector.'
                );
            }

            return $pes;
        }

        if ($idPesPost > 0) {
            $pes = ProfesionalEfectorServicioModel::findOne(['id' => $idPesPost, 'deleted_at' => null]);
            if ($pes === null || (int) $pes->id_efector !== $idEfector) {
                throw new \InvalidArgumentException('Asignación inválida para este efector.');
            }

            return $pes;
        }

        throw new \InvalidArgumentException(
            'Indicá el profesional y el servicio a dar de baja (id_profesional_efector_servicio e id_servicio).'
        );
    }
}
