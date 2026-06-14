<?php

namespace common\components\Domain\Scheduling\Assistant;

use common\components\Platform\Ui\UiSelectOptionSourceProviderInterface;

/**
 * Opciones de selects de turnos (profesionales, slots).
 */
final class SchedulingUiSelectOptionSourceProvider implements UiSelectOptionSourceProviderInterface
{
    public static function providerKey(): string
    {
        return 'scheduling';
    }

    /**
     * @param mixed $filter
     * @param array<string, mixed> $params
     * @param array<string, mixed> $optionConfig
     * @return list<array<string, mixed>>
     */
    public static function resolve(string $sourceKey, $filter, array $params, array $optionConfig): array
    {
        return match ($sourceKey) {
            'profesionales', 'profesional-efector-servicio' => SchedulingUiSelectOptionsService::resolveProfesionales(
                $sourceKey,
                $filter,
                $params,
                $optionConfig
            ),
            'slots_disponibles_paciente' => SchedulingUiSelectOptionsService::resolveSlotsDisponiblesPaciente($params),
            default => [],
        };
    }
}
