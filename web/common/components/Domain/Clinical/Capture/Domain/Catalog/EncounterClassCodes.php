<?php

namespace common\components\Domain\Clinical\Capture\Domain\Catalog;

/**
 * Códigos FHIR de clase de encounter usados por catálogos de captura.
 * Alineados con {@see \common\models\Clinical\Encounter} (sin depender del AR).
 */
final class EncounterClassCodes
{
    public const AMB = 'AMB';
    public const IMP = 'IMP';
    public const EMER = 'EMER';
    public const OBSENC = 'OBSENC';
    public const VR = 'VR';
    public const HH = 'HH';
}
