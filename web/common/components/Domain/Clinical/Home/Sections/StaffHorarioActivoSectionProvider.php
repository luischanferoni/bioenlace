<?php

namespace common\components\Domain\Clinical\Home\Sections;

use common\components\Domain\Organization\Efector\Application\Authorization\EfectorOperationAccess;
use common\components\Domain\Organization\Pes\Application\UseCase\ActivateProfesionalHorario;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\models\Clinical\Encounter;
use Yii;

/**
 * Horario activo según encounter_class del panel (EMER o IMP).
 */
final class StaffHorarioActivoSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $encounterClass = strtoupper(trim((string) ($context['encounter_class'] ?? '')));
        if ($encounterClass !== Encounter::ENCOUNTER_CLASS_EMER
            && $encounterClass !== Encounter::ENCOUNTER_CLASS_IMP) {
            $encounterClass = Encounter::ENCOUNTER_CLASS_EMER;
        }

        $idEfector = $this->resolveIdEfector($context, $encounterClass);

        $at = null;
        if (!empty($context['fecha'])) {
            $at = trim((string) $context['fecha']) . ' ' . date('H:i:s');
        }

        return ActivateProfesionalHorario::homePanelGatePayload($idEfector, $encounterClass, $at);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveIdEfector(array $context, string $encounterClass): int
    {
        $fromContext = (int) ($context['id_efector'] ?? 0);
        if ($encounterClass === Encounter::ENCOUNTER_CLASS_EMER) {
            $params = $fromContext > 0 ? ['id_efector' => $fromContext] : [];
            try {
                return EfectorOperationAccess::assertAndResolveIdEfector('GuardiaEpisode.view_board', $params);
            } catch (DomainOperationForbiddenException $e) {
                throw new \InvalidArgumentException(
                    $e->getMessage() !== '' ? $e->getMessage() : 'No autorizado.',
                    0,
                    $e
                );
            }
        }

        if ($fromContext > 0) {
            return $fromContext;
        }
        $session = (int) (Yii::$app->user->getIdEfector() ?? 0);
        if ($session <= 0) {
            throw new \InvalidArgumentException('Se requiere id_efector.');
        }

        return $session;
    }
}
