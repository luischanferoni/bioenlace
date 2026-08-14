<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Presentation\ProfesionalesDistribucionInfoPresentation;
use common\components\Platform\Core\DataAccess\MetricExecutionResult;
use common\components\Platform\Core\DataAccess\QueryOutputMode;

class ProfesionalesDistribucionInfoPresentationTest extends Unit
{
    public function testSingleServiceSentence(): void
    {
        $result = $this->result([
            ['total' => 6, 'id_servicio' => 7, 'servicio_nombre' => 'MED GENERAL'],
        ]);

        $params = (new ProfesionalesDistribucionInfoPresentation())->buildRenderParams($result);

        $this->assertSame('Profesionales por servicio', $params['info_title']);
        $this->assertSame('En Hospital Demo hay 6 profesionales en MED GENERAL.', $params['info_texto']);
    }

    public function testMultipleServicesList(): void
    {
        $result = $this->result([
            ['total' => 2, 'id_servicio' => 3, 'servicio_nombre' => 'PEDIATRÍA'],
            ['total' => 6, 'id_servicio' => 7, 'servicio_nombre' => 'MED GENERAL'],
        ]);

        $texto = (new ProfesionalesDistribucionInfoPresentation())->buildResumenTexto($result);

        $this->assertStringContainsString('En Hospital Demo hay profesionales en 2 servicios:', $texto);
        $this->assertStringContainsString('• MED GENERAL: 6 profesionales', $texto);
        $this->assertStringContainsString('• PEDIATRÍA: 2 profesionales', $texto);
        $posMed = strpos($texto, 'MED GENERAL');
        $posPed = strpos($texto, 'PEDIATRÍA');
        $this->assertNotFalse($posMed);
        $this->assertNotFalse($posPed);
        $this->assertLessThan($posPed, $posMed);
    }

    public function testEmptyGroups(): void
    {
        $texto = (new ProfesionalesDistribucionInfoPresentation())->buildResumenTexto($this->result([]));

        $this->assertSame('En Hospital Demo no hay profesionales asignados a servicios.', $texto);
    }

    /**
     * @param list<array<string, mixed>> $groups
     */
    private function result(array $groups): MetricExecutionResult
    {
        return new MetricExecutionResult(
            'profesionales_conteo_por_servicio_efector',
            QueryOutputMode::GROUPED,
            ['count_by_servicio' => 6],
            [],
            $groups,
            [],
            false,
            ['id_efector' => 1510, 'nombre_efector' => 'Hospital Demo', 'group_count' => count($groups)]
        );
    }
}
