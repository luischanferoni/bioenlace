<?php

namespace common\tests\unit\permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\BioenlaceRbacRevision;
use Yii;

final class BioenlaceRbacRevisionTest extends Unit
{
    protected function _before(): void
    {
        BioenlaceRbacRevision::resetForTests();
    }

    public function testBumpIncreasesRevision(): void
    {
        if (!Yii::$app->has('cache') || Yii::$app->cache === null) {
            $this->markTestSkipped('Cache no disponible en el entorno de test.');
        }

        $before = BioenlaceRbacRevision::current();
        BioenlaceRbacRevision::bump();
        $after = BioenlaceRbacRevision::current();

        $this->assertGreaterThan($before, $after);
    }
}
