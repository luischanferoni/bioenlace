<?php

namespace common\tests\unit\platform\auth;

use common\components\Platform\Core\Auth\StaffMobileAccessService;
use PHPUnit\Framework\TestCase;

final class StaffMobileAccessServiceTest extends TestCase
{
    public function testAllowsUnassignedIdentityOnlyAccountToOpenSessionWizard(): void
    {
        self::assertTrue(StaffMobileAccessService::isIdentityOnlyAccountAllowed([], true, false));
        self::assertTrue(StaffMobileAccessService::isIdentityOnlyAccountAllowed(['usuario'], true, false));
    }

    public function testDoesNotTreatAnAssignedRoleOrEfectorAsIdentityOnly(): void
    {
        self::assertFalse(StaffMobileAccessService::isIdentityOnlyAccountAllowed(['AdminEfector'], true, false));
        self::assertFalse(StaffMobileAccessService::isIdentityOnlyAccountAllowed(['Medico'], true, false));
        self::assertFalse(StaffMobileAccessService::isIdentityOnlyAccountAllowed(['usuario'], true, true));
    }

    public function testRequiresAnAssociatedPersona(): void
    {
        self::assertFalse(StaffMobileAccessService::isIdentityOnlyAccountAllowed([], false, false));
    }
}
