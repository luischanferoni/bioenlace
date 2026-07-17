<?php

namespace common\tests\unit\platform;

use Codeception\Test\Unit;
use common\components\Platform\Ai\SpeechToText\AudioBatchAssembler;

class AudioBatchAssemblerTest extends Unit
{
    public function testAssembleEmptyReturnsNull(): void
    {
        $this->assertNull(AudioBatchAssembler::assembleToTempFile([]));
        $this->assertNull(AudioBatchAssembler::assembleToTempFile(['/no/existe.m4a']));
    }

    public function testAssembleSingleReturnsOriginalPath(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'stt_test_');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, str_repeat('x', 64));
        try {
            $out = AudioBatchAssembler::assembleToTempFile([$tmp]);
            $this->assertSame($tmp, $out);
        } finally {
            @unlink($tmp);
        }
    }

    public function testContentFingerprintStable(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'stt_fp_');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, 'abc');
        try {
            $a = AudioBatchAssembler::contentFingerprint([$tmp]);
            $b = AudioBatchAssembler::contentFingerprint([$tmp]);
            $this->assertSame($a, $b);
            $this->assertNotSame('', $a);
        } finally {
            @unlink($tmp);
        }
    }
}
