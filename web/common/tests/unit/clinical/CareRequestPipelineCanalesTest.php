<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\CareRequest\Domain\CodingSystems;
use common\components\Domain\Clinical\CareRequest\Domain\InMemoryServiceLineActCatalog;
use common\components\Domain\Clinical\CareRequest\Infrastructure\External\InMemoryCareRequestActCoder;
use common\components\Domain\Clinical\CareRequest\Domain\Model\CareRequest;
use common\components\Domain\Clinical\CareRequest\Application\Service\CareRequestMetadata;
use common\components\Domain\Clinical\CareRequest\Application\UseCase\SubmitPatientCareRequest;
use common\components\Domain\Clinical\CareRequest\Application\UseCase\ResolveCareRequest;
use common\models\Clinical\Input\DerivacionInput;

class CareRequestPipelineCanalesTest extends Unit
{
    protected function _before(): void
    {
        CareRequestMetadata::resetCacheForTests();
        DerivacionInput::setActoCoderForTests(null);
    }

    protected function _after(): void
    {
        DerivacionInput::setActoCoderForTests(null);
    }

    public function testResolveNoAplicaDefaultCuandoHayDisplaySinCode(): void
    {
        $catalog = new InMemoryServiceLineActCatalog(
            [
                [
                    'code' => '183515008',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Referral to physician',
                ],
            ],
            [
                [
                    'linea_id' => 11,
                    'linea_label' => 'RADIOLOGIA',
                    'code' => '183515008',
                    'system' => CodingSystems::SNOMED,
                    'preferente' => true,
                ],
            ]
        );
        $svc = new ResolveCareRequest($catalog);
        $result = $svc->resolve(new CareRequest(
            11,
            null,
            null,
            CareRequest::MODO_INTERCONSULTA,
            null,
            null,
            'ecografía abdominal'
        ));

        $this->assertFalse($result['complete']);
        $this->assertContains('acto', $result['missing']);
        $this->assertNull($result['pedido']->actoCode);
        $this->assertSame('ecografía abdominal', $result['pedido']->actoDisplay);
    }

    public function testDerivacionCodingUnHitCompletaActo(): void
    {
        DerivacionInput::setActoCoderForTests(new InMemoryCareRequestActCoder([
            'ecografía' => [
                'resolved' => [
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Diagnostic ultrasonography',
                ],
            ],
        ]));

        $input = DerivacionInput::fromExtractedRow([
            'id_servicio' => 11,
            'Acto' => 'ecografía',
            'Modo' => CareRequest::MODO_ESTUDIO,
        ]);

        $this->assertSame('16310003', $input->actoCode);
        $this->assertSame(CodingSystems::SNOMED, $input->actoSystem);
        $this->assertSame([], $input->missingFieldsForCompleteness());
    }

    public function testDerivacionCodingNCandidatosIssuesChips(): void
    {
        DerivacionInput::setActoCoderForTests(new InMemoryCareRequestActCoder([
            'eco' => [
                'resolved' => null,
                'candidates' => [
                    [
                        'code' => '16310003',
                        'system' => CodingSystems::SNOMED,
                        'display' => 'Diagnostic ultrasonography',
                    ],
                    [
                        'code' => '71651007',
                        'system' => CodingSystems::SNOMED,
                        'display' => 'Mammography',
                    ],
                ],
            ],
        ]));

        $input = DerivacionInput::fromExtractedRow([
            'id_servicio' => 11,
            'Acto' => 'eco',
            'Modo' => CareRequest::MODO_ESTUDIO,
        ]);

        $this->assertNull($input->actoCode);
        $issues = $input->buildIssues('ConsultaDerivaciones', 0);
        $actoIssue = null;
        foreach ($issues as $issue) {
            if (($issue['field'] ?? '') === DerivacionInput::FIELD_ACTO_DISPLAY) {
                $actoIssue = $issue;
                break;
            }
        }
        $this->assertNotNull($actoIssue);
        $this->assertCount(2, $actoIssue['options']);
        $this->assertFalse($actoIssue['allow_custom']);
    }

    public function testPromptFieldNamesIncluyeModo(): void
    {
        $this->assertContains(DerivacionInput::FIELD_MODO, DerivacionInput::promptFieldNames());
        $cfg = CareRequestMetadata::actoCodingConfig();
        $this->assertSame('procedimientos', $cfg['snomed_category']);
    }

    public function testPacienteMatchTextoUnReservable(): void
    {
        $catalog = new InMemoryServiceLineActCatalog(
            [
                [
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Diagnostic ultrasonography',
                ],
            ],
            [
                [
                    'linea_id' => 11,
                    'linea_label' => 'RADIOLOGIA',
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'preferente' => true,
                ],
            ]
        );
        $svc = new SubmitPatientCareRequest(new ResolveCareRequest($catalog), $catalog);
        $draft = [
            'triage_raiz' => SubmitPatientCareRequest::TRIAGE_RAIZ_ESTUDIO,
            'pedido_acto' => 'ultrasonography',
        ];
        $svc->aplicarFlagsEnDraft($draft);

        $this->assertSame(CodingSystems::SNOMED . '|16310003', $draft['pedido_acto']);
        $this->assertSame('11', (string) $draft['id_servicio_asignado']);
    }
}
