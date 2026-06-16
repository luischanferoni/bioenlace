<?php

namespace common\tests\unit\ui;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\ProductMetadataPaths;
use common\components\Platform\Ui\Home\Service\HomePanelManifest;
use Symfony\Component\Yaml\Yaml;

class HomePanelManifestTest extends Unit
{
    protected function _after(): void
    {
        HomePanelManifest::resetCacheForTests();
    }

    public function testAudienceStaffRolesFromMetadata(): void
    {
        $manifest = new HomePanelManifest();
        $roles = $manifest->audienceStaffRoles();

        $this->assertNotEmpty($roles);
        $this->assertContains('Medico', $roles);
        $this->assertNotContains('paciente', $roles);
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

        $this->assertContains('staff_internacion_kpis', $ids);
        $this->assertContains('inpatients', $ids);
    }
}
