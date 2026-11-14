<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

/**
 * Contrato de una categoría de captura que se guarda como panel clínico.
 *
 * Un panel agrupa observaciones por sesión o control y guarda un campo por valor.
 * Implementarlo permite que {@see \common\components\Domain\Clinical\Specialty\Application\Service\ClinicalPanelService}
 * persista y lea el panel sin conocer la especialidad.
 */
interface ClinicalPanelContract
{
    /**
     * Clave del panel en `observation_panel_ext.panel_kind`.
     */
    public static function panelKind(): string;

    /**
     * Categoría de las observaciones del panel.
     */
    public static function observationCategory(): string;

    /**
     * Claves que la IA devuelve para la categoría.
     *
     * @return list<string>
     */
    public static function promptFields(): array;

    /**
     * Valores normalizados, con la clave del prompt como clave.
     *
     * @param array<mixed> $payload
     * @return array<string, string>
     */
    public static function normalize(array $payload): array;

    /**
     * Cómo se guarda un campo: código, sistema, tipo de valor y unidad.
     *
     * @return array{code: string, system: string, type: string, unit: ?string}|null
     */
    public static function storageFor(string $field): ?array;
}
