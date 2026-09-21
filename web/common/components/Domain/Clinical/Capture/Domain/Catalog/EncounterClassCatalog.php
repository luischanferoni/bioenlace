<?php

namespace common\components\Domain\Clinical\Capture\Domain\Catalog;

/**
 * Catálogo de códigos FHIR de clase de encounter usados por captura.
 * Alineados con {@see \common\models\Clinical\Encounter} (sin depender del AR).
 */
final class EncounterClassCatalog
{
    public const AMB = 'AMB';
    public const IMP = 'IMP';
    public const EMER = 'EMER';
    public const OBSENC = 'OBSENC';
    public const VR = 'VR';
    public const HH = 'HH';
}
