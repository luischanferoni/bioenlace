<?php

namespace common\tests\unit\ui;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\CapabilityManifestIndex;
use common\components\Platform\Core\Product\ProductMetadataPaths;
use common\components\Platform\Ui\Home\Service\HomePanelManifest;
use Symfony\Component\Yaml\Yaml;

class HomePanelManifestTest extends Unit
{
    protected function _after(): void
    {
        HomePanelManifest::resetCacheForTests();
        CapabilityManifestIndex::resetCacheForTests();
    }

    public function testAudienceStaffRolesFromMetadata(): void
    {
        $manifest = new HomePanelManifest();
        $roles = $manifest->audienceStaffRoles();

        $this->assertNotEmpty($roles);
        $this->assertContains('Medico', $roles);
        $this->assertNotContains('paciente', $roles);
    }

    public function testEmergencyCapabilityIdsFromManifest(): void
    {
        $manifest = new HomePanelManifest();

        $this->assertSame('guardia.triage', $manifest->emergencyCapabilityId('triage'));
        $this->assertSame('guardia.ingreso', $manifest->emergencyCapabilityId('ingreso'));
        $this->assertSame('guardia.atender', $manifest->emergencyCapabilityId('atender'));
        $this->assertSame('encounter.documentar_nota', $manifest->emergencyCapabilityId('documentar'));
        $this->assertSame('guardia.retiro_administrativo', $manifest->emergencyCapabilityId('retiro_administrativo'));
    }

    public function testEmergencyTriageRolesExcludeMedico(): void
    {
        $manifest = new HomePanelManifest();
        $roles = $manifest->emergencyTriageRoles();

        $this->assertNotEmpty($roles);
        $this->assertContains('enfermeria', $roles);
        $this->assertContains('Administrativo', $roles);
        $this->assertNotContains('Medico', $roles);
    }

    public function testEmergencyIngresoRolesAreAdministrativoNotEnfermeria(): void
    {
        $manifest = new HomePanelManifest();
        $roles = $manifest->emergencyIngresoRoles();

        $this->assertNotEmpty($roles);
        $this->assertContains('Administrativo', $roles);
        $this->assertContains('AdminEfector', $roles);
        $this->assertNotContains('enfermeria', $roles);
        $this->assertNotContains('Medico', $roles);
    }

    public function testEmergencyIngresoDniClientsIncludeWebAndMobile(): void
    {
        $manifest = new HomePanelManifest();
        $clients = $manifest->emergencyIngresoDniClients();

        $this->assertContains('mobile', $clients);
        $this->assertContains('web', $clients);
    }

    public function testEmergencyAtenderRolesAreMedicoOnly(): void
    {
        $manifest = new HomePanelManifest();
        $roles = $manifest->emergencyAtenderRoles();

        $this->assertSame(['Medico'], $roles);
        $exclude = $manifest->emergencyAtenderExcludeRoles();
        $this->assertContains('Administrativo', $exclude);
        $this->assertContains('AdminEfector', $exclude);
        $this->assertNotContains('Medico', $exclude);
    }

    public function testEmergencyDocumentarRolesAreEnfermeria(): void
    {
        $manifest = new HomePanelManifest();
        $roles = $manifest->emergencyDocumentarRoles();

        $this->assertSame(['enfermeria'], $roles);
        $this->assertNotContains('Medico', $roles);
        $this->assertNotContains('Administrativo', $roles);
    }

    public function testAudiencePatientRoleFromMetadata(): void
    {
        $manifest = new HomePanelManifest();
        $this->assertSame('paciente', $manifest->audiencePatientRole());
    }

    public function testStaffOperationsPanelHasSessionContextAndKpis(): void
    {
        $manifest = new HomePanelManifest();
        $panel = $manifest->resolveForStaff(null);

        $this->assertSame('staff_dashboard', $panel['layout']);
        $sectionIds = array_column($panel['sections'], 'id');
        $this->assertContains('staff_session_context', $sectionIds);
        $this->assertContains('staff_agenda_kpis', $sectionIds);
        $this->assertNotContains('action_cards', $sectionIds);
    }

    public function testAmbPanelIncludesAgendaKpisBeforeAppointments(): void
    {
        $manifest = new HomePanelManifest();
        $panel = $manifest->resolveForStaff('AMB');
        $sectionIds = array_column($panel['sections'], 'id');

        $this->assertSame(['staff_agenda_kpis', 'appointments_day'], $sectionIds);
    }

    public function testVrPanelIsBandejaOnlyWithoutAsyncKpis(): void
    {
        $manifest = new HomePanelManifest();
        $panel = $manifest->resolveForStaff('VR');
        $sectionIds = array_column($panel['sections'], 'id');

        $this->assertSame(['async_consultations_queue'], $sectionIds);
        $this->assertSame('Consultas clínicas por mensaje', $panel['title']);
    }

    public function testImpSurgicalManifestSliceDefinesSurgeryKpis(): void
    {
        $path = ProductMetadataPaths::homePanelManifestFile();
        $this->assertFileExists($path);
        $raw = Yaml::parseFile($path);
        $sections = $raw['panels']['staff']['IMP']['imp_surgical']['sections'] ?? [];
        $ids = array_column($sections, 'id');

        $this->assertContains('staff_surgery_kpis', $ids);
        $this->assertContains('surgeries_day', $ids);
    }

    public function testImpFloorManifestSliceDefinesInternacionKpis(): void
    {
        $path = ProductMetadataPaths::homePanelManifestFile();
        $raw = Yaml::parseFile($path);
        $sections = $raw['panels']['staff']['IMP']['imp_floor']['sections'] ?? [];
        $ids = array_column($sections, 'id');

        $this->assertContains('staff_cobertura_activa', $ids);
        $this->assertContains('staff_internacion_kpis', $ids);
        $this->assertContains('inpatients', $ids);
    }

    public function testEmerPanelIncludesCoberturaActiva(): void
    {
        $path = ProductMetadataPaths::homePanelManifestFile();
        $raw = Yaml::parseFile($path);
        $sections = $raw['panels']['staff']['EMER']['sections'] ?? [];
        $ids = array_column($sections, 'id');

        $this->assertContains('staff_cobertura_activa', $ids);
        $this->assertContains('staff_guardia_kpis', $ids);
        $this->assertContains('emergency_board', $ids);
        $this->assertSame('staff_cobertura_activa', $ids[0] ?? null);
        $this->assertNotContains('emergency_indicators', $ids);
    }
}
