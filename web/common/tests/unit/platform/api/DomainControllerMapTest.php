<?php

namespace common\tests\unit\platform\api;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\ApiRoutePermissionResolver;
use frontend\modules\api\v1\DomainControllerMap;
use Yii;

/**
 * Invariante del árbol espejo: agrupar controllers por dominio no cambia el id público,
 * así que no cambian las URLs ni las rutas RBAC derivadas del `uniqueId`.
 */
final class DomainControllerMapTest extends Unit
{
    /** @var array<string, string> id público → dominio esperado */
    private const IDS_ESPERADOS = [
        'audio' => 'clinical',
        'care-packs' => 'clinical',
        'encounter-chat' => 'clinical',
        'encounter-journey' => 'clinical',
        'media' => 'clinical',
        'motivos-consulta' => 'clinical',
        'pacientes' => 'clinical',
        'consulta-async' => 'scheduling',
        'consultas-seguimiento' => 'scheduling',
        'profesional-agenda' => 'scheduling',
        'quirofano' => 'scheduling',
        'turnos' => 'scheduling',
        'turnos-perfil' => 'scheduling',
        'catalogos' => 'organization',
        'efectores' => 'organization',
        'licencia' => 'organization',
        'profesional-efector-servicio' => 'organization',
        'profesional-horarios' => 'organization',
        'servicios' => 'organization',
        'servicio-teleconsulta-politica' => 'organization',
        'sesion-operativa' => 'organization',
        'solicitud-profesional' => 'organization',
        'paciente-contexto' => 'person',
        'persona' => 'person',
        'person-representation' => 'person',
        'registro' => 'person',
        'ventanilla-sesion' => 'person',
        'whats-app-webhook' => 'integrations',
        'encounter' => 'clinical',
        'care-plan' => 'clinical',
    ];

    public function testIdPublicoNoLlevaDominio(): void
    {
        $map = DomainControllerMap::build();

        foreach (self::IDS_ESPERADOS as $id => $domain) {
            $this->assertArrayHasKey($id, $map, "Falta el id público '$id'");
            $this->assertStringContainsString(
                '\\controllers\\' . $domain . '\\',
                $map[$id],
                "El id '$id' no apunta al dominio '$domain'"
            );
        }
    }

    public function testCadaControllerDeDominioTieneUnIdUnico(): void
    {
        $dir = Yii::getAlias('@frontend') . '/modules/api/v1/controllers';
        $archivos = glob($dir . '/*/*Controller.php') ?: [];

        $this->assertNotEmpty($archivos);
        $this->assertCount(
            count($archivos),
            DomainControllerMap::build(),
            'Hay controllers de dominio con el mismo nombre de clase en distintos dominios.'
        );
    }

    public function testLaClaseDeclaraElNamespaceDeSuCarpeta(): void
    {
        foreach (DomainControllerMap::build() as $id => $fqcn) {
            $this->assertTrue(class_exists($fqcn), "No carga la clase de '$id': $fqcn");
        }
    }

    public function testRutaRbacHistoricaSigueVigente(): void
    {
        // El uniqueId se arma con el id público (sin dominio), no con la carpeta.
        $routes = ApiRoutePermissionResolver::checkedRoutesForAction(
            'api/v1/turnos/listar-como-paciente',
            'v1/turnos/listar-como-paciente'
        );

        $this->assertContains('/api/turnos/listar-como-paciente', $routes);
    }

    public function testAliasDeIdPublicoSiguenDeclaradosEnConfig(): void
    {
        $config = file_get_contents(Yii::getAlias('@frontend') . '/config/main.php');

        // Ids públicos que no coinciden con el nombre de la clase: van a mano en controllerMap.
        foreach (['servicio-teleconsulta', 'whatsapp', 'consulta-chat'] as $alias) {
            $this->assertStringContainsString("'" . $alias . "' =>", (string) $config);
        }
    }
}
