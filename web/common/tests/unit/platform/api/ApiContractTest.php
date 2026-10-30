<?php

namespace common\tests\unit\platform\api;

use common\components\Platform\Core\Http\Contract\ApiErrorCatalog;
use common\components\Platform\Core\Http\Contract\ApiResponder;
use PHPUnit\Framework\TestCase;

class ApiContractTest extends TestCase
{
    public function testCadaEntradaDelCatalogoEsCompleta(): void
    {
        foreach (ApiErrorCatalog::all() as $code => $entry) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $code);
            $this->assertNotEmpty($entry['message'] ?? '', $code);
            $this->assertContains($entry['intent'] ?? null, ['error', 'warning', 'info'], $code);
            $this->assertIsBool($entry['retryable'] ?? null, $code);
            $this->assertGreaterThanOrEqual(400, (int) ($entry['http_status'] ?? 0), $code);
        }
    }

    public function testErrorTieneLaFormaDelContrato(): void
    {
        $result = ApiResponder::error('validation_failed', [], ['nombre' => ['Requerido']]);

        $this->assertSame(422, $result['status']);
        $this->assertSame(['error'], array_keys($result['body']));
        $this->assertSame(
            ['code', 'message', 'intent', 'retryable', 'field_errors'],
            array_keys($result['body']['error'])
        );
    }

    public function testGoneDetailUsaElEstadoHttp410(): void
    {
        $result = ApiResponder::error('gone_detail', ['detail' => 'El alta profesional se trasladó a la app.']);

        $this->assertSame(410, $result['status']);
        $this->assertSame('gone_detail', $result['body']['error']['code']);
        $this->assertSame('El alta profesional se trasladó a la app.', $result['body']['error']['message']);
    }

    public function testCodigoDesconocidoCaeEnErrorInterno(): void
    {
        $result = ApiResponder::error('codigo_que_no_existe');

        $this->assertSame(ApiErrorCatalog::FALLBACK_CODE, $result['body']['error']['code']);
        $this->assertSame(500, $result['status']);
    }

    public function testExitoTieneDataYMeta(): void
    {
        $body = ApiResponder::success(['a' => 1]);

        $this->assertSame(['data', 'meta'], array_keys($body));
        $this->assertSame('{}', json_encode($body['meta']));
    }

    public function testErrorConMetaLoIncluyeEnElCuerpo(): void
    {
        $result = ApiResponder::error('capture_not_found', [], [], ['capture' => ['id' => 1]]);

        $this->assertSame(['error', 'meta'], array_keys($result['body']));
        $this->assertSame(['id' => 1], $result['body']['meta']['capture']);
    }

    public function testDetailUsaElTextoPorDefectoSiFalta(): void
    {
        $result = ApiResponder::error('capture_blocking_error', ['detail' => '']);

        $this->assertSame('No se puede guardar: el análisis tiene errores.', $result['body']['error']['message']);
    }

    public function testTodoCodigoUsadoEnControllersYDominioExisteEnElCatalogo(): void
    {
        $base = dirname(__DIR__, 5);
        $roots = [
            $base . '/frontend/modules/api/v1/controllers',
            $base . '/common/components/Domain',
        ];
        $used = [];
        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                if (preg_match_all('/(?:->fail|->failure|->errorMessage|new ApiException)\(\s*\'([a-z0-9_]+)\'/', $source, $m)) {
                    foreach ($m[1] as $code) {
                        $used[$code] = $file->getFilename();
                    }
                }
            }
        }
        $this->assertNotEmpty($used);
        foreach ($used as $code => $file) {
            $this->assertTrue(ApiErrorCatalog::has($code), "Código '$code' usado en $file no está en el catálogo");
        }
    }
}
