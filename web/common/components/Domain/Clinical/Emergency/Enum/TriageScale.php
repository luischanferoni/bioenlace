<?php

namespace common\components\Domain\Clinical\Emergency\Enum;

final class TriageScale
{
    public const MANCHESTER = 'manchester';

    public static function isValid(string $scale): bool
    {
        return $scale === self::MANCHESTER;
    }

    /**
     * @return array<int, array{label: string, color: string}>
     */
    public static function levelMeta(): array
    {
        return [
            1 => ['label' => 'Rojo — inmediato', 'color' => '#c0392b'],
            2 => ['label' => 'Naranja — muy urgente', 'color' => '#e67e22'],
            3 => ['label' => 'Amarillo — urgente', 'color' => '#f1c40f'],
            4 => ['label' => 'Verde — estándar', 'color' => '#27ae60'],
            5 => ['label' => 'Azul — no urgente', 'color' => '#3498db'],
        ];
    }
}
