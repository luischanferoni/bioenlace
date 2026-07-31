<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\models\Servicio;

class ServicioFindByNameTermsTest extends Unit
{
    public function testCardiologiaTermsIncludeCardiologo(): void
    {
        $terms = Servicio::getSearchTermsForNombre('CARDIOLOGIA');
        $this->assertContains('cardiologia', $terms);
        $this->assertContains('cardiologo', $terms);
    }

    public function testCompoundNameDoesNotInventShortColloquial(): void
    {
        $terms = Servicio::getSearchTermsForNombre('MED CLINICA');
        $this->assertContains('med clinica', $terms);
        // No generar "clinico" suelto: eso forzaría clínico → MED CLINICA por heurística.
        $this->assertNotContains('clinico', $terms);
    }
}
