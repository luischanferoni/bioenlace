<?php

namespace common\components\Home\Service\Sections;

/**
 * Proveedor de datos para una sección del panel de inicio.
 */
interface HomePanelSectionProviderInterface
{
    /**
     * @param array<string, mixed> $context fecha, prueba, id_efector, etc.
     * @return array<string, mixed>
     */
    public function build(array $context): array;
}
