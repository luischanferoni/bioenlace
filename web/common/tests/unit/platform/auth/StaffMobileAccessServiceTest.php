<?php

namespace common\tests\unit\platform\auth;

use common\components\Platform\Core\Auth\StaffMobileAccessService;
use PHPUnit\Framework\TestCase;

final class StaffMobileAccessServiceTest extends TestCase
{
    public function testAllowsUnassignedIdentityOnlyAccountToOpenSessionWizard(): void
    {
        self::assertTrue(StaffMobileAccessService::isMobileAccountAllowed([], true, false));
        self::assertTrue(StaffMobileAccessService::isMobileAccountAllowed(['usuario'], true, false));
        self::assertTrue(StaffMobileAccessService::isMobileAccountAllowed(['paciente'], true, false));
        self::assertTrue(StaffMobileAccessService::isMobileAccountAllowed(['usuario', 'paciente'], true, false));
    }

    public function testDoesNotTreatAnAssignedRoleOrEfectorAsIdentityOnly(): void
    {
        self::assertFalse(StaffMobileAccessService::isMobileAccountAllowed(['AdminEfector'], true, false));
        self::assertFalse(StaffMobileAccessService::isMobileAccountAllowed(['Medico'], true, false));
        self::assertFalse(StaffMobileAccessService::isMobileAccountAllowed(['usuario'], true, true));
        self::assertTrue(StaffMobileAccessService::isMobileAccountAllowed(['Medico'], true, true));
        self::assertFalse(StaffMobileAccessService::isMobileAccountAllowed(['paciente'], true, true));
    }

    public function testRequiresAnAssociatedPersona(): void
    {
        self::assertFalse(StaffMobileAccessService::isMobileAccountAllowed([], false, false));
    }

    public function testOnlyExplicitClinicalRolesAreAllowedInTheMobileApp(): void
    {
        self::assertTrue(StaffMobileAccessService::hasMobileStaffRole(['Medico']));
        self::assertTrue(StaffMobileAccessService::hasMobileStaffRole(['enfermeria_x_efector_12']));
        self::assertFalse(StaffMobileAccessService::hasMobileStaffRole(['AdminEfector']));
        self::assertFalse(StaffMobileAccessService::hasMobileStaffRole(['Administrativo']));
        self::assertFalse(StaffMobileAccessService::hasMobileStaffRole(['facturista']));
    }

    public function testIdentifiesPersonalHealthRequestsWithoutBlockingPatientApp(): void
    {
        self::assertTrue(StaffMobileAccessService::isPersonalSaludRequest('mobile', 'personalsalud-flutter'));
        self::assertTrue(StaffMobileAccessService::isPersonalSaludRequest('mobile', 'bioenlace-personalsalud'));
        self::assertFalse(StaffMobileAccessService::isPersonalSaludRequest('mobile', 'bioenlace-paciente'));
        self::assertFalse(StaffMobileAccessService::isPersonalSaludRequest('web', 'personalsalud-flutter'));
    }
}
