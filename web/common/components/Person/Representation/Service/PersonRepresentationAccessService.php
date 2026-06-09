<?php

namespace common\components\Person\Representation\Service;

use common\components\Person\Representation\Enum\DelegationConsentStatus;
use common\components\Person\Representation\Enum\PersonRelatedStatus;
use common\components\Person\Representation\Enum\PersonRelatedVerifiedBy;
use common\components\Person\Representation\Enum\RepresentationRegime;
use common\models\Person\PersonDelegationConsent;
use common\models\Person\PersonRelated;

/**
 * Autorización única para actuar en nombre de otro paciente (tutela A o delegación B).
 */
final class PersonRepresentationAccessService
{
    private RepresentationPermissionsCatalog $permissionsCatalog;

    public function __construct(?RepresentationPermissionsCatalog $permissionsCatalog = null)
    {
        $this->permissionsCatalog = $permissionsCatalog ?? new RepresentationPermissionsCatalog();
    }

    public function canAct(int $actorPersonaId, int $subjectPersonaId, string $permission): bool
    {
        if ($actorPersonaId <= 0 || $subjectPersonaId <= 0) {
            return false;
        }
        if ($actorPersonaId === $subjectPersonaId) {
            return true;
        }

        $link = PersonRelated::findActiveLink($actorPersonaId, $subjectPersonaId);
        if ($link === null) {
            return false;
        }

        $consent = null;
        if ($link->regime === RepresentationRegime::PATIENT_DELEGATION) {
            $consent = PersonDelegationConsent::findActiveForLink((int) $link->id);
        }

        return self::evaluateAccess($link, $consent, $permission, $this->permissionsCatalog);
    }

    public static function evaluateAccess(
        PersonRelated $link,
        ?PersonDelegationConsent $consent,
        string $permission,
        ?RepresentationPermissionsCatalog $permissionsCatalog = null
    ): bool {
        $catalog = $permissionsCatalog ?? new RepresentationPermissionsCatalog();

        if (!PersonRelatedStatus::isOperative((string) $link->status)) {
            return false;
        }

        if ($link->regime === RepresentationRegime::VERIFIED_GUARDIANSHIP) {
            if ((string) $link->verified_by === PersonRelatedVerifiedBy::NONE) {
                return false;
            }
        }

        if ($link->regime === RepresentationRegime::PATIENT_DELEGATION) {
            if ($consent === null || (string) $consent->status !== DelegationConsentStatus::ACTIVE) {
                return false;
            }
        }

        return $catalog->linkGrantsPermission($link, $consent, $permission);
    }
}
