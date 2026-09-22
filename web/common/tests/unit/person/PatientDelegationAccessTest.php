<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Representation\Domain\Model\DelegationConsentStatus;
use common\components\Domain\Person\Representation\Domain\Model\PersonRelatedStatus;
use common\components\Domain\Person\Representation\Domain\Model\PersonRelatedVerifiedBy;
use common\components\Domain\Person\Representation\Domain\Model\RepresentationPermission;
use common\components\Domain\Person\Representation\Domain\Model\RepresentationRegime;
use common\components\Domain\Person\Representation\Application\Authorization\PersonRepresentationAccess;
use common\components\Domain\Person\Representation\Domain\Catalog\RepresentationPermissionsCatalog;
use common\models\Person\PersonDelegationConsent;
use common\models\Person\PersonRelated;

/**
 * Revocación régimen B corta canAct de inmediato (sin DB).
 */
class PatientDelegationAccessTest extends Unit
{
    protected function _before(): void
    {
        RepresentationPermissionsCatalog::resetCacheForTests();
    }

    public function testActiveDelegationAllowsAccess(): void
    {
        [$link, $consent] = $this->activeDelegation();

        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            $consent,
            RepresentationPermission::SCHEDULING_TURNO
        ))->true();
    }

    public function testRevokedLinkDeniesAccessEvenWithStaleConsentObject(): void
    {
        [$link, $consent] = $this->activeDelegation();
        $link->status = PersonRelatedStatus::REVOKED;

        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            $consent,
            RepresentationPermission::SCHEDULING_TURNO
        ))->false();
    }

    public function testRevokedConsentDeniesAccess(): void
    {
        [$link, $consent] = $this->activeDelegation();
        $consent->status = DelegationConsentStatus::REVOKED;

        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            $consent,
            RepresentationPermission::CLINICAL_MOTIVOS
        ))->false();
    }

    public function testNullConsentDeniesAccess(): void
    {
        [$link] = $this->activeDelegation();

        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            null,
            RepresentationPermission::SCHEDULING_TURNO
        ))->false();
    }

    /**
     * @return array{0: PersonRelated, 1: PersonDelegationConsent}
     */
    private function activeDelegation(): array
    {
        $link = new PersonRelated();
        $link->id = 42;
        $link->subject_persona_id = 10;
        $link->actor_persona_id = 20;
        $link->regime = RepresentationRegime::PATIENT_DELEGATION;
        $link->status = PersonRelatedStatus::ACTIVE;
        $link->verified_by = PersonRelatedVerifiedBy::NONE;

        $consent = new PersonDelegationConsent();
        $consent->id = 1;
        $consent->person_related_id = 42;
        $consent->status = DelegationConsentStatus::ACTIVE;
        $consent->provision_json = json_encode([
            'permissions' => RepresentationPermission::v1Defaults(),
        ]);

        return [$link, $consent];
    }
}
