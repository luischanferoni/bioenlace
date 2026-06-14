<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;

class PersonRepresentationSubjectServiceTest extends Unit
{
    public function testPhase4PermissionCodes(): void
    {
        verify(RepresentationPermission::SCHEDULING_TURNO)->equals('scheduling.turno');
        verify(RepresentationPermission::CLINICAL_MOTIVOS)->equals('clinical.motivos');
        verify(RepresentationPermission::CLINICAL_CARE_PACK_ASSISTANCE)->equals('clinical.care_pack_assistance');
        verify(RepresentationPermission::CLINICAL_CARE_PLAN)->equals('clinical.care_plan');
        verify(RepresentationPermission::CLINICAL_HISTORIA_RESUMEN)->equals('clinical.historia_resumen');
    }
}
