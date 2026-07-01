<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\IntentFamilyCatalog;
use common\components\Platform\Core\Permission\IntentManifestIndex;
use common\components\Platform\Core\Permission\IntentManifestMetadata;
use common\components\Platform\Core\Permission\Validation\CatalogIntegrityService;
use Symfony\Component\Yaml\Yaml;

class IntentManifestMetadataTest extends Unit
{
    protected function _before(): void
    {
        IntentManifestIndex::resetCache();
        IntentFamilyCatalog::resetCache();
    }

    public function testPilotCondicionLaboralIntentIsValid(): void
    {
        $path = dirname(__DIR__, 5)
            . '/metadata/bioenlace/assistant/intents/update/condicion-laboral.editar-propio.yaml';
        $this->assertFileExists($path);

        $data = Yaml::parseFile($path);
        $this->assertIsArray($data);

        $result = IntentManifestMetadata::validate(
            'condicion-laboral.editar-propio',
            'update',
            $data
        );

        $this->assertSame([], $result['errors'], implode("\n", $result['errors']));
        $this->assertSame([], $result['warnings'], implode("\n", $result['warnings']));
    }

    public function testInvalidDomainOperationIsRejected(): void
    {
        $result = IntentManifestMetadata::validate('test.intent', 'update', [
            'operation' => 'edit',
            'intent_family' => 'test.family',
            'domain_operation' => 'NoExiste.operacion',
            'fields' => [['name' => 'campo_a']],
        ]);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('domain_operation', $result['errors'][0]);
    }

    public function testFieldGroupMustReferenceDeclaredFields(): void
    {
        $result = IntentManifestMetadata::validate('test.intent', 'update', [
            'operation' => 'edit',
            'intent_family' => 'test.family',
            'domain_operation' => 'ProfesionalEfectorServicio.condicion_laboral_own',
            'fields' => [['name' => 'fecha_inicio']],
            'field_groups' => [
                'vigencia' => [
                    'fields' => ['fecha_inicio', 'fecha_fin'],
                ],
            ],
        ]);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('fecha_fin', $result['errors'][0]);
    }

    public function testIntentFamilyCatalogLoadsPilotFamily(): void
    {
        $family = IntentFamilyCatalog::get('condicion-laboral.edit');
        $this->assertNotNull($family);
        $this->assertSame('edit', $family['operation']);
        $this->assertContains('condicion-laboral.editar-propio', $family['members']);
        $this->assertContains('condicion-laboral.editar-staff', $family['members']);
    }

    public function testIntentManifestIndexIndexesExtendedMetadata(): void
    {
        $meta = IntentManifestIndex::get('condicion-laboral.editar-staff');
        $this->assertNotNull($meta);
        $this->assertSame('edit', $meta['operation']);
        $this->assertSame('condicion-laboral.edit', $meta['intent_family']);
        $this->assertSame('ProfesionalEfectorServicio.condicion_laboral_staff', $meta['domain_operation']);
        $this->assertContains('id_condicion_laboral', $meta['fields']);
        $this->assertTrue($meta['uses_extended_contract']);
    }

    public function testCatalogIntegrityAcceptsPilotIntentFamily(): void
    {
        $result = (new CatalogIntegrityService())->run();
        $familyErrors = array_values(array_filter(
            $result['errors'],
            static fn (string $msg): bool => str_contains($msg, 'condicion-laboral')
        ));

        $this->assertEmpty(
            $familyErrors,
            "Errores integridad piloto condicion-laboral:\n" . implode("\n", $familyErrors)
        );
    }

    public function testFormatDisplayActionNameUsesYamlLabelAsIs(): void
    {
        $label = IntentManifestMetadata::formatDisplayActionName('Mi condición laboral', 'edit');
        $this->assertSame('Mi condición laboral', $label);
    }

    public function testFormatDisplayActionNameDoesNotAlterExistingVerb(): void
    {
        $label = IntentManifestMetadata::formatDisplayActionName('Ver agenda del día', 'read');
        $this->assertSame('Ver agenda del día', $label);
    }

    public function testResolveCrudToneMapsOperations(): void
    {
        $this->assertSame('create', IntentManifestMetadata::resolveCrudTone('create'));
        $this->assertSame('read', IntentManifestMetadata::resolveCrudTone('list'));
        $this->assertSame('update', IntentManifestMetadata::resolveCrudTone('edit'));
        $this->assertSame('delete', IntentManifestMetadata::resolveCrudTone('delete'));
    }

    public function testResolveDisplayActionNameForClientUsesYamlLabelForAllClients(): void
    {
        $paciente = IntentManifestMetadata::resolveDisplayActionNameForClient(
            'Necesito atención',
            'create',
            'paciente-flutter'
        );
        $this->assertSame('Necesito atención', $paciente);

        $staff = IntentManifestMetadata::resolveDisplayActionNameForClient(
            'Necesito atención',
            'create',
            'personalsalud-flutter'
        );
        $this->assertSame('Necesito atención', $staff);
    }

    public function testIntentManifestIndexFormatsDisplayActionName(): void
    {
        $meta = IntentManifestIndex::get('condicion-laboral.editar-propio');
        $this->assertNotNull($meta);
        $this->assertSame('Mi condición laboral', $meta['action_name']);
        $this->assertSame('update', $meta['crud_tone']);
    }
}
