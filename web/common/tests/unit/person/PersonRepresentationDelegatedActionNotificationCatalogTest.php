<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Representation\Application\Catalog\PersonRepresentationDelegatedActionNotificationCatalog;
use common\models\Person\PersonRelatedAuditLog;

final class PersonRepresentationDelegatedActionNotificationCatalogTest extends Unit
{
    protected function _after(): void
    {
        PersonRepresentationDelegatedActionNotificationCatalog::resetCacheForTests();
    }

    public function testReturnsTemplatesForEveryAuditedActionAndFallback(): void
    {
        $catalog = new PersonRepresentationDelegatedActionNotificationCatalog();
        $expectedBodies = [
            PersonRelatedAuditLog::ACTION_TURNO_CREATED => '{actor_label} sacó un turno a tu nombre.',
            PersonRelatedAuditLog::ACTION_TURNO_CANCELLED => '{actor_label} canceló un turno a tu nombre.',
            PersonRelatedAuditLog::ACTION_MOTIVOS_SENT => '{actor_label} cargó motivos de consulta a tu nombre.',
            PersonRelatedAuditLog::ACTION_CARE_PACK_ASSISTANCE => '{actor_label} completó el cuestionario pre-consulta a tu nombre.',
            PersonRelatedAuditLog::ACTION_CARE_PACK_FOLLOWUP => '{actor_label} registró el seguimiento post-consulta a tu nombre.',
            PersonRelatedAuditLog::ACTION_HISTORIA_ACCESSED => '{actor_label} consultó tu historia clínica.',
            PersonRelatedAuditLog::ACTION_CARE_PLAN_ACCESSED => '{actor_label} consultó tu plan de tratamiento.',
        ];

        foreach ($expectedBodies as $action => $bodyTemplate) {
            $message = $catalog->forAction($action);
            $this->assertSame('Acción de tu representante', $message['title']);
            $this->assertSame($bodyTemplate, $message['body_template']);
        }

        $this->assertSame(
            '{actor_label} realizó una gestión a tu nombre.',
            $catalog->forAction('unknown_action')['body_template']
        );
    }
}
