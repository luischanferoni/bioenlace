<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Application\Service\CaptureBatchTextComposer;
use common\components\Platform\Core\Http\Contract\ApiException;

class CaptureBatchTextComposerTest extends Unit
{
    public function testUnaSolaNotaSeDevuelveSinMarcador(): void
    {
        $this->assertSame('dolor de cabeza', CaptureBatchTextComposer::compose(['  dolor de cabeza ']));
    }

    public function testVariasNotasSeUnenEnOrdenSinMarcadores(): void
    {
        $text = CaptureBatchTextComposer::compose(['uno', 'dos']);
        $this->assertSame("uno\n\ndos", $text);
    }

    public function testNotaSinTextoFalla(): void
    {
        $this->expectExceptionObject(new ApiException('capture_batch_note_not_ready'));
        CaptureBatchTextComposer::compose(['uno', ' ']);
    }

    public function testSinNotasFalla(): void
    {
        $this->expectExceptionObject(new ApiException('capture_no_transcript'));
        CaptureBatchTextComposer::compose([]);
    }

    public function testMasDelTopeDeNotasFalla(): void
    {
        $this->expectException(ApiException::class);
        CaptureBatchTextComposer::compose(array_fill(0, CaptureBatchTextComposer::MAX_NOTES + 1, 'x'));
    }

    public function testMasDelTopeDeCaracteresFalla(): void
    {
        $this->expectException(ApiException::class);
        CaptureBatchTextComposer::compose([
            str_repeat('a', CaptureBatchTextComposer::MAX_CHARS),
            'b',
        ]);
    }

    public function testIdsDelLoteSinRepetidos(): void
    {
        $ids = CaptureBatchTextComposer::clientIdsFromBody([
            'batch_client_capture_ids' => ['a', 'b', 'a', ' ', 'c'],
        ]);
        $this->assertSame(['a', 'b', 'c'], $ids);
        $this->assertSame([], CaptureBatchTextComposer::clientIdsFromBody([]));
    }
}
