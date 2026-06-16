<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Domain\Clinical\Home\StaffClinicalDayListService;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\models\Cirugia;
use Yii;

/**
 * KPIs del día quirúrgico (encounter IMP, servicio quirúrgico).
 */
final class StaffSurgeryKpiSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Se requiere efector en sesión.');
        }

        $fecha = (string) ($context['fecha'] ?? date('Y-m-d'));
        $items = (new StaffClinicalDayListService())->cirugiasAgendadasPorEfectorYFecha($fecha);

        $pendientes = 0;
        $enCurso = 0;
        $realizadas = 0;
        $canceladas = 0;

        foreach ($items as $row) {
            $estado = (string) ($row['estado'] ?? '');
            if ($estado === Cirugia::ESTADO_EN_CURSO) {
                $enCurso++;
            } elseif ($estado === Cirugia::ESTADO_REALIZADA) {
                $realizadas++;
            } elseif (in_array($estado, [Cirugia::ESTADO_CANCELADA, Cirugia::ESTADO_SUSPENDIDA], true)) {
                $canceladas++;
            } elseif (in_array($estado, [Cirugia::ESTADO_CONFIRMADA, Cirugia::ESTADO_LISTA_ESPERA], true)) {
                $pendientes++;
            }
        }

        $total = count($items);

        return [
            'title' => 'Quirófano',
            'items' => [
                [
                    'label' => 'Cirugías del día',
                    'value' => (string) $total,
                ],
                [
                    'label' => 'Pendientes',
                    'value' => (string) $pendientes,
                ],
                [
                    'label' => 'En curso',
                    'value' => (string) $enCurso,
                ],
                [
                    'label' => 'Realizadas',
                    'value' => (string) $realizadas,
                ],
                [
                    'label' => 'Canceladas / suspendidas',
                    'value' => (string) $canceladas,
                ],
            ],
            'fecha' => $fecha,
        ];
    }
}
