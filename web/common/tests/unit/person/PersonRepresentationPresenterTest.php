<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Representation\Enum\PersonRelatedStatus;
use common\components\Domain\Person\Representation\Enum\RepresentationRegime;
use common\components\Domain\Person\Representation\Service\PersonRepresentationPresenter;
use common\models\Person\PersonRelated;
use common\models\Person\RelationshipType;

class PersonRepresentationPresenterTest extends Unit
{
    public function testLinkToArrayIncludesCoreFields(): void
    {
        $type = new RelationshipType();
        $type->code = 'padre';
        $type->label = 'Padre';

        $link = new PersonRelated();
        $link->id = 7;
        $link->subject_persona_id = 100;
        $link->actor_persona_id = 200;
        $link->relationship_type_id = 1;
        $link->regime = RepresentationRegime::VERIFIED_GUARDIANSHIP;
        $link->status = PersonRelatedStatus::PENDING;
        $link->verified_by = 'none';
        $link->created_at = '2026-06-02 10:00:00';
        $link->updated_at = '2026-06-02 10:00:00';
        $link->populateRelation('relationshipType', $type);

        $presenter = new PersonRepresentationPresenter();
        $out = $presenter->linkToArray($link, false, false);

        verify($out['id'])->equals(7);
        verify($out['status'])->equals('pending');
        verify($out['relationship_type']['code'])->equals('padre');
        verify(array_key_exists('subject', $out))->false();
    }
}
