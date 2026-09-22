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

class PersonRepresentationAccessTest extends Unit
{
    protected function _before(): void
    {
        RepresentationPermissionsCatalog::resetCacheForTests();
    }

    public function testRegimenAActiveVerifiedCanActOnSchedulingTurno(): void
    {
        $link = $this->makeLink(
            RepresentationRegime::VERIFIED_GUARDIANSHIP,
            PersonRelatedStatus::ACTIVE,
            PersonRelatedVerifiedBy::STAFF
        );

        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            null,
            RepresentationPermission::SCHEDULING_TURNO
        ))->true();
    }

    public function testTwoFathersBothActiveCanAct(): void
    {
        $father1 = $this->makeLink(
            RepresentationRegime::VERIFIED_GUARDIANSHIP,
            PersonRelatedStatus::ACTIVE,
            PersonRelatedVerifiedBy::STAFF,
            10,
            100
        );
        $father2 = $this->makeLink(
            RepresentationRegime::VERIFIED_GUARDIANSHIP,
            PersonRelatedStatus::ACTIVE,
            PersonRelatedVerifiedBy::STAFF,
            20,
            100
        );

        verify(PersonRepresentationAccess::evaluateAccess(
            $father1,
            null,
            RepresentationPermission::SCHEDULING_TURNO
        ))->true();
        verify(PersonRepresentationAccess::evaluateAccess(
            $father2,
            null,
            RepresentationPermission::CLINICAL_MOTIVOS
        ))->true();
    }

    public function testBlockedLinkCannotAct(): void
    {
        $link = $this->makeLink(
            RepresentationRegime::VERIFIED_GUARDIANSHIP,
            PersonRelatedStatus::BLOCKED,
            PersonRelatedVerifiedBy::STAFF
        );

        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            null,
            RepresentationPermission::SCHEDULING_TURNO
        ))->false();
    }

    public function testRegimenBWithoutActiveConsentCannotAct(): void
    {
        $link = $this->makeLink(
            RepresentationRegime::PATIENT_DELEGATION,
            PersonRelatedStatus::ACTIVE,
            PersonRelatedVerifiedBy::NONE
        );

        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            null,
            RepresentationPermission::SCHEDULING_TURNO
        ))->false();

        $revokedConsent = new PersonDelegationConsent();
        $revokedConsent->status = DelegationConsentStatus::REVOKED;

        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            $revokedConsent,
            RepresentationPermission::SCHEDULING_TURNO
        ))->false();
    }

    public function testRegimenBWithActiveConsentCanAct(): void
    {
        $link = $this->makeLink(
            RepresentationRegime::PATIENT_DELEGATION,
            PersonRelatedStatus::ACTIVE,
            PersonRelatedVerifiedBy::NONE
        );
        $consent = new PersonDelegationConsent();
        $consent->status = DelegationConsentStatus::ACTIVE;

        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            $consent,
            RepresentationPermission::CLINICAL_HISTORIA_RESUMEN
        ))->true();
    }

    public function testRegimenAPendingNotVerifiedCannotAct(): void
    {
        $link = $this->makeLink(
            RepresentationRegime::VERIFIED_GUARDIANSHIP,
            PersonRelatedStatus::ACTIVE,
            PersonRelatedVerifiedBy::NONE
        );

        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            null,
            RepresentationPermission::SCHEDULING_TURNO
        ))->false();
    }

    public function testExplicitPermissionSnapshotRestrictsAccess(): void
    {
        $link = $this->makeLink(
            RepresentationRegime::PATIENT_DELEGATION,
            PersonRelatedStatus::ACTIVE,
            PersonRelatedVerifiedBy::NONE
        );
        $link->permissions_json = json_encode([
            'permissions' => [RepresentationPermission::CLINICAL_MOTIVOS],
        ]);
        $consent = new PersonDelegationConsent();
        $consent->status = DelegationConsentStatus::ACTIVE;

        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            $consent,
            RepresentationPermission::CLINICAL_MOTIVOS
        ))->true();
        verify(PersonRepresentationAccess::evaluateAccess(
            $link,
            $consent,
            RepresentationPermission::SCHEDULING_TURNO
        ))->false();
    }

    private function makeLink(
        string $regime,
        string $status,
        string $verifiedBy,
        int $actorId = 1,
        int $subjectId = 2
    ): PersonRelated {
        $link = new PersonRelated();
        $link->id = 1;
        $link->actor_persona_id = $actorId;
        $link->subject_persona_id = $subjectId;
        $link->relationship_type_id = 1;
        $link->regime = $regime;
        $link->status = $status;
        $link->verified_by = $verifiedBy;

        return $link;
    }
}
