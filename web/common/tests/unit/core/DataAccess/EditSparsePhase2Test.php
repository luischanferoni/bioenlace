<?php

namespace common\tests\unit\core\DataAccess;

use Codeception\Test\Unit;
use common\components\Platform\Core\DataAccess\AttributeGroupCatalog;
use common\components\Platform\Core\DataAccess\Edit\EditSparseAspectIds;
use common\components\Platform\Core\DataAccess\Edit\EditSparseConfirmPresenter;
use common\components\Platform\Core\DataAccess\Edit\EditSparseFieldBuilder;
use common\components\Platform\Core\DataAccess\PermissionContext;

class EditSparsePhase2Test extends Unit
{
    protected function _after(): void
    {
        AttributeGroupCatalog::resetCacheForTests();
    }

    public function testAspectIdsParserAcceptsCsvAndArray(): void
    {
        $this->assertSame(['identidad'], EditSparseAspectIds::parse('identidad'));
        $this->assertSame(['identidad', 'agenda_horarios'], EditSparseAspectIds::parse('identidad, agenda_horarios'));
        $this->assertSame(['a', 'b'], EditSparseAspectIds::parse(['a', 'b', 'b']));
        $this->assertSame(['identidad'], EditSparseAspectIds::fromParams(['aspect_id' => 'identidad']));
    }

    public function testConfirmPresenterBuildsDiff(): void
    {
        $presenter = new EditSparseConfirmPresenter();
        $baseline = [
            'identidad' => [
                'nombre' => 'Juan',
                'apellido' => 'Pérez',
            ],
        ];
        $proposed = [
            'nombre' => 'Pedro',
            'apellido' => 'Pérez',
        ];

        $diff = $presenter->buildDiff($baseline, $proposed, ['identidad']);
        $this->assertTrue($diff['has_changes']);
        $this->assertCount(1, $diff['changes']);
        $this->assertSame('nombre', $diff['changes'][0]['field']);
        $this->assertStringContainsString('Pedro', $presenter->formatPreviewText('Pérez, Juan', $diff));
    }

    public function testFieldBuilderProducesIdentidadFieldsForAdmin(): void
    {
        $ctx = new PermissionContext(1, ['AdminEfector']);
        $builder = new EditSparseFieldBuilder();

        $built = $builder->build(
            'ProfesionalEfectorServicio',
            ['nombre', 'apellido'],
            [
                'nombre' => ['nombre' => 'Ana'],
                'apellido' => ['apellido' => 'López'],
            ],
            ['id_efector' => '1'],
            $ctx
        );

        $this->assertContains('nombre', $built['aspect_ids']);
        $this->assertContains('apellido', $built['aspect_ids']);
        $names = array_map(static fn (array $f): string => (string) ($f['name'] ?? ''), $built['fields']);
        $this->assertContains('nombre', $names);
        $this->assertContains('apellido', $names);

        $nombreField = null;
        foreach ($built['fields'] as $field) {
            if (($field['name'] ?? '') === 'nombre') {
                $nombreField = $field;
                break;
            }
        }
        $this->assertIsArray($nombreField);
        $this->assertSame('Ana', $nombreField['value']);
    }
}
