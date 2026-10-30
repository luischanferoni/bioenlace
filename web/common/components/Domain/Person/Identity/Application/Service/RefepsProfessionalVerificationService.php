<?php

namespace common\components\Domain\Person\Identity\Application\Service;

use common\models\Person\Persona;
use Yii;

final class RefepsProfessionalVerificationService
{
    /**
     * The currently available SISA adapter only queries its Santiago registry scope.
     * A negative result is therefore not a nationwide REFEPS determination.
     */
    public const PROVIDER_SCOPE = 'santiago';

    public function verify(Persona $persona): RefepsVerificationResult
    {
        if (!Yii::$app->has('sisa')) {
            return $this->unavailable();
        }

        try {
            $document = self::normalizeDocument((string) $persona->documento);
            if ($document === '') {
                return $this->reviewRequired();
            }

            $sisa = Yii::$app->get('sisa');
            if (!method_exists($sisa, 'getProfesionalesDeSantiago')) {
                return $this->unavailable();
            }

            $fullName = (string) $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);
            $lastName = (string) $persona->apellido;
            $firstName = (string) $persona->nombre;
            if (strpos($fullName, ',') !== false) {
                [$lastName, $firstName] = array_map('trim', explode(',', $fullName, 2));
            }

            $response = $sisa->getProfesionalesDeSantiago($lastName, $firstName, '', $document);
            return self::parseResponse($response, $document);
        } catch (\Throwable $e) {
            Yii::warning(
                'No se pudo completar la consulta REFEPS/SISA (' . get_class($e) . ').',
                'refeps'
            );
            return $this->unavailable();
        }
    }

    /**
     * @param mixed $response
     */
    public static function parseResponse($response, string $document): RefepsVerificationResult
    {
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                return new RefepsVerificationResult(
                    RefepsVerificationResult::UNAVAILABLE,
                    'No pudimos interpretar la respuesta de REFEPS.'
                );
            }
        } elseif (is_array($response)) {
            $decoded = $response;
        } else {
            return new RefepsVerificationResult(
                RefepsVerificationResult::UNAVAILABLE,
                'No recibimos una respuesta válida de REFEPS.'
            );
        }

        if (($decoded['ok'] ?? null) !== true) {
            return new RefepsVerificationResult(
                RefepsVerificationResult::UNAVAILABLE,
                'No pudimos completar la consulta de REFEPS.'
            );
        }

        $professionals = $decoded['profesionales'] ?? null;
        $total = isset($decoded['total']) && is_numeric($decoded['total'])
            ? (int) $decoded['total']
            : null;

        if ($total === 0) {
            if (is_array($professionals) && $professionals !== []) {
                return new RefepsVerificationResult(
                    RefepsVerificationResult::REVIEW_REQUIRED,
                    'La respuesta de REFEPS es inconsistente. No se realizó la asignación.'
                );
            }

            return new RefepsVerificationResult(
                RefepsVerificationResult::NOT_FOUND,
                'No se encontró una matrícula en la consulta disponible. Revisá los datos o solicitá una revisión.'
            );
        }

        if (!is_array($professionals) || $professionals === []) {
            if ($total === null) {
                return new RefepsVerificationResult(
                    RefepsVerificationResult::NOT_FOUND,
                    'No se encontró una matrícula en la consulta disponible. Revisá los datos o solicitá una revisión.'
                );
            }

            return new RefepsVerificationResult(
                RefepsVerificationResult::REVIEW_REQUIRED,
                'La respuesta de REFEPS requiere revisión. No se realizó la asignación.'
            );
        }

        $expectedDocument = self::normalizeDocument($document);
        $hasDocumentData = false;
        foreach ($professionals as $professional) {
            if (!is_array($professional)) {
                continue;
            }
            $recordDocuments = self::extractDocuments($professional);
            if ($recordDocuments === []) {
                continue;
            }
            $hasDocumentData = true;
            foreach ($recordDocuments as $recordDocument) {
                if ($recordDocument === $expectedDocument) {
                    return new RefepsVerificationResult(
                        RefepsVerificationResult::CONFIRMED,
                        'La identidad profesional fue confirmada en la consulta disponible.'
                    );
                }
            }
        }

        if (!$hasDocumentData) {
            return new RefepsVerificationResult(
                RefepsVerificationResult::REVIEW_REQUIRED,
                'No pudimos confirmar que el resultado corresponda a esta persona. No se realizó la asignación.'
            );
        }

        return new RefepsVerificationResult(
            RefepsVerificationResult::REVIEW_REQUIRED,
            'Los datos devueltos por REFEPS no coinciden con la persona. No se realizó la asignación.'
        );
    }

    private static function extractDocuments(array $professional): array
    {
        $keys = [
            'dni',
            'documento',
            'nro_documento',
            'numero_documento',
            'numeroDocumento',
            'nroDocumento',
            'document_number',
        ];
        $documents = [];
        foreach ($keys as $key) {
            if (isset($professional[$key]) && is_scalar($professional[$key])) {
                $normalized = self::normalizeDocument((string) $professional[$key]);
                if ($normalized !== '') {
                    $documents[] = $normalized;
                }
            }
        }

        return array_values(array_unique($documents));
    }

    private static function normalizeDocument(string $document): string
    {
        return preg_replace('/\D+/', '', $document) ?? '';
    }

    private function unavailable(): RefepsVerificationResult
    {
        return new RefepsVerificationResult(
            RefepsVerificationResult::UNAVAILABLE,
            'No pudimos consultar REFEPS. Reintentá más tarde; no se realizó la asignación.'
        );
    }

    private function reviewRequired(): RefepsVerificationResult
    {
        return new RefepsVerificationResult(
            RefepsVerificationResult::REVIEW_REQUIRED,
            'No pudimos confirmar los datos profesionales. No se realizó la asignación.'
        );
    }
}
