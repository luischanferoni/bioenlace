<?php

namespace common\tests\unit\integrations;

use Codeception\Test\Unit;
use common\components\Domain\Integrations\Identity\DiditClient;

final class DiditClientTestable extends DiditClient
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function identityFromPayload(array $data, string $verificationId): array
    {
        return $this->buildIdentityResultFromPayload($data, $verificationId, 'test');
    }
}

class DiditClientTest extends Unit
{
    public function testApprovedV3SessionDecisionMapsIdentityFields(): void
    {
        $client = new DiditClientTestable();
        $result = $client->identityFromPayload([
            'session_id' => '4c5c7f3a-1f82-4f3b-8d8e-1a8d2d2f9b7a',
            'status' => 'Approved',
            'vendor_data' => 'paciente-42',
            'id_verifications' => [
                [
                    'status' => 'Approved',
                    'first_name' => 'Mercedes',
                    'last_name' => 'Diaz',
                    'date_of_birth' => '1984-01-01',
                    'gender' => 'F',
                    'document_number' => '29486884',
                    'document_type' => 'Identity Card',
                ],
            ],
        ], '4c5c7f3a-1f82-4f3b-8d8e-1a8d2d2f9b7a');

        $this->assertTrue($result['success']);
        $this->assertSame('approved', $result['status']);
        $this->assertSame('29486884', $result['documento']);
        $this->assertSame('Mercedes', $result['nombre']);
        $this->assertSame('Diaz', $result['apellido']);
        $this->assertSame(1, $result['sexo_biologico']);
        $this->assertSame(1, $result['genero']);
        $this->assertSame('paciente-42', $result['didit_reference_id']);
    }

    public function testMaleSexMapsToBioenlaceConvention(): void
    {
        $client = new DiditClientTestable();
        $result = $client->identityFromPayload([
            'session_id' => 'session-m',
            'status' => 'Approved',
            'id_verifications' => [
                [
                    'status' => 'Approved',
                    'first_name' => 'Juan',
                    'last_name' => 'Perez',
                    'gender' => 'M',
                    'document_number' => '30111222',
                    'date_of_birth' => '1990-05-15',
                ],
            ],
        ], 'session-m');

        $this->assertSame(2, $result['sexo_biologico']);
        $this->assertSame(2, $result['genero']);
    }

    public function testDeclinedStatusIsRejected(): void
    {
        $client = new DiditClientTestable();
        $result = $client->identityFromPayload([
            'session_id' => 'session-1',
            'status' => 'Declined',
            'id_verifications' => [],
        ], 'session-1');

        $this->assertFalse($result['success']);
        $this->assertSame('rejected', $result['status']);
    }
}
