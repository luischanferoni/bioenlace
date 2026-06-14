<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Representation\Enum\DelegationConsentStatus;
use common\components\Domain\Person\Representation\Enum\PersonRelatedStatus;
use common\components\Domain\Person\Representation\Enum\PersonRelatedVerifiedBy;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Enum\RepresentationRegime;
use common\components\Domain\Person\Representation\Service\PersonRepresentationAccessService;
use common\components\Domain\Person\Representation\Service\RepresentationPermissionsCatalog;
use common\models\Person\PersonDelegationConsent;
use common\models\Person\PersonRelated;

class PersonRepresentationAccessServiceTest extends Unit
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

        verify(PersonRepresentationAccessService::evaluateAccess(
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

        verify(PersonRepresentationAccessService::evaluateAccess(
            $father1,
            null,
            RepresentationPermission::SCHEDULING_TURNO
        ))->true();
        verify(PersonRepresentationAccessService::evaluateAccess(
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

        verify(PersonRepresentationAccessService::evaluateAccess(
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

        verify(PersonRepresentationAccessService::evaluateAccess(
            $link,
            null,
            RepresentationPermission::SCHEDULING_TURNO
        ))->false();

        $revokedConsent = new PersonDelegationConsent();
        $revokedConsent->status = DelegationConsentStatus::REVOKED;

        verify(PersonRepresentationAccessService::evaluateAccess(
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

        verify(PersonRepresentationAccessService::evaluateAccess(
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

        verify(PersonRepresentationAccessService::evaluateAccess(
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

        verify(PersonRepresentationAccessService::evaluateAccess(
            $link,
            $consent,
            RepresentationPermission::CLINICAL_MOTIVOS
        ))->true();
        verify(PersonRepresentationAccessService::evaluateAccess(
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
