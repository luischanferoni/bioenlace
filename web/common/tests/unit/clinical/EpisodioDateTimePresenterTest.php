<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Encounter\Application\Presentation\EpisodioDateTimePresenter;

class EpisodioDateTimePresenterTest extends Unit
{
    public function testDisplayDateTime(): void
    {
        $this->assertSame('07/08/2026 12:19', EpisodioDateTimePresenter::display('2026-08-07 12:19:55'));
        $this->assertSame('07/08/2026 12:19', EpisodioDateTimePresenter::display('2026-08-07T12:19:55'));
        $this->assertSame('07/08/2026', EpisodioDateTimePresenter::display('2026-08-07'));
        $this->assertSame('07/08/2026 16:24', EpisodioDateTimePresenter::displayFromParts('2026-08-07', '16:24:39'));
        $this->assertSame('', EpisodioDateTimePresenter::display(''));
        $this->assertSame('07/08/2026 12:19', EpisodioDateTimePresenter::display('07/08/2026 12:19'));
    }
}
