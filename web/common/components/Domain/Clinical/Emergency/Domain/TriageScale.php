<?php

namespace common\components\Domain\Clinical\Emergency\Domain;

final class TriageScale
{
    public const MANCHESTER = 'manchester';
    public const DEFAULT_LEVEL = 3;

    public static function isValid(string $scale): bool
    {
        return $scale === self::MANCHESTER;
    }

    /**
     * @return array<int, array{label: string, color: string, intent: string}>
     */
    public static function levelMeta(): array
    {
        return [
            1 => ['label' => 'Rojo — inmediato', 'color' => '#c0392b', 'intent' => 'danger'],
            2 => ['label' => 'Naranja — muy urgente', 'color' => '#e67e22', 'intent' => 'danger'],
            3 => ['label' => 'Amarillo — urgente', 'color' => '#f1c40f', 'intent' => 'warning'],
            4 => ['label' => 'Verde — estándar', 'color' => '#27ae60', 'intent' => 'success'],
            5 => ['label' => 'Azul — no urgente', 'color' => '#3498db', 'intent' => 'info'],
        ];
    }

    /**
     * @return list<array{level:int,label:string,color:string,intent:string,is_default:bool}>
     */
    public static function levelMetadata(): array
    {
        $out = [];
        foreach (self::levelMeta() as $level => $metadata) {
            $out[] = [
                'level' => (int) $level,
                'label' => $metadata['label'],
                'color' => $metadata['color'],
                'intent' => $metadata['intent'],
                'is_default' => (int) $level === self::DEFAULT_LEVEL,
            ];
        }

        return $out;
    }
}
