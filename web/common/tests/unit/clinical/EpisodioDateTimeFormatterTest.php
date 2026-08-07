<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Presentation\EpisodioDateTimeFormatter;

class EpisodioDateTimeFormatterTest extends Unit
{
    public function testDisplayDateTime(): void
    {
        $this->assertSame('07/08/2026 12:19', EpisodioDateTimeFormatter::display('2026-08-07 12:19:55'));
        $this->assertSame('07/08/2026 12:19', EpisodioDateTimeFormatter::display('2026-08-07T12:19:55'));
        $this->assertSame('07/08/2026', EpisodioDateTimeFormatter::display('2026-08-07'));
        $this->assertSame('07/08/2026 16:24', EpisodioDateTimeFormatter::displayFromParts('2026-08-07', '16:24:39'));
        $this->assertSame('', EpisodioDateTimeFormatter::display(''));
        $this->assertSame('07/08/2026 12:19', EpisodioDateTimeFormatter::display('07/08/2026 12:19'));
    }
}
