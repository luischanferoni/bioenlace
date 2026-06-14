<?php

namespace common\components\Ui\Home\Service;

/**
 * Resuelve variantes de panel staff declaradas en el manifiesto YAML (p. ej. IMP → imp_surgical | imp_floor).
 *
 * Implementaciones de dominio registradas en {@see common/config/product-registries.php}.
 */
interface HomePanelStaffPanelSliceResolverInterface
{
    public function applies(string $encounterClass): bool;

    /**
     * @param array<string, mixed> $staffPanelDef entrada `panels.staff.<encounter_class>` del manifiesto
     * @return array<string, mixed> panel con `layout`, `title`, `sections`
     */
    public function resolve(array $staffPanelDef): array;
}
