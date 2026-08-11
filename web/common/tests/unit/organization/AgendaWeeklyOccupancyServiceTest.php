<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Service\AgendaWeeklyOccupancyService;

class AgendaWeeklyOccupancyServiceTest extends Unit
{
    public function testParseHourCsvDedupsAndSorts(): void
    {
        $this->assertSame([8, 9, 10, 11], AgendaWeeklyOccupancyService::parseHourCsv('11,8,9,8,10'));
        $this->assertSame([], AgendaWeeklyOccupancyService::parseHourCsv(''));
        $this->assertSame([0, 23], AgendaWeeklyOccupancyService::parseHourCsv('0,23,24,-1,x'));
    }

    public function testIntersectingHoursAtHourLevel(): void
    {
        $proposed = [
            'lunes_2' => [8, 9, 10, 11],
            'martes_2' => [22, 23],
        ];
        $busy = [
            'lunes_2' => [8, 9, 10, 11, 12, 13, 14, 15, 16, 17],
            'miercoles_2' => [8, 9],
        ];
        $hit = AgendaWeeklyOccupancyService::intersectingHours($proposed, $busy);
        $this->assertSame([8, 9, 10, 11], $hit['lunes_2']);
        $this->assertArrayNotHasKey('martes_2', $hit);
        $this->assertArrayNotHasKey('miercoles_2', $hit);
    }

    public function testNightHoursDoNotConflictWithDaytimeBusy(): void
    {
        $proposed = ['lunes_2' => [20, 21, 22, 23]];
        $busy = ['lunes_2' => [8, 9, 10, 11, 12, 13, 14, 15, 16, 17]];
        $this->assertSame([], AgendaWeeklyOccupancyService::intersectingHours($proposed, $busy));
    }

    public function testSubtractBusyAndOverlapError(): void
    {
        $proposed = ['lunes_2' => [8, 9, 20]];
        $busy = ['lunes_2' => [8, 9, 10]];
        $kept = AgendaWeeklyOccupancyService::subtractBusy($proposed, $busy);
        $this->assertSame([20], $kept['lunes_2']);

        $msg = AgendaWeeklyOccupancyService::overlapError(['lunes_2' => '8,9,20'], $busy);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('Lunes', (string) $msg);
        $this->assertNull(AgendaWeeklyOccupancyService::overlapError(['lunes_2' => '20,21'], $busy));
    }

    public function testProposedHoursFromDatetimeRangeMondayMorning(): void
    {
        $hours = AgendaWeeklyOccupancyService::proposedHoursFromDatetimeRange(
            '2026-08-10 08:00:00',
            '2026-08-10 12:00:00'
        );
        $this->assertSame([8, 9, 10, 11], $hours['lunes_2']);
        $this->assertSame([], $hours['martes_2']);
    }

    public function testProposedHoursOvernight(): void
    {
        $hours = AgendaWeeklyOccupancyService::proposedHoursFromDatetimeRange(
            '2026-08-10 22:00:00',
            '2026-08-11 06:00:00'
        );
        $this->assertSame([22, 23], $hours['lunes_2']);
        $this->assertSame([0, 1, 2, 3, 4, 5], $hours['martes_2']);
    }

    public function testAttachBusyStripsInitialValuesAndSetsProps(): void
    {
        $ui = [
            'blocks' => [[
                'kind' => 'fields',
                'fields' => [[
                    'name' => 'weekly_scheduler_widget',
                    'widget_id' => 'weekly_scheduler',
                    'initial_values' => [
                        'lunes_2' => '8,9,10,11',
                        'martes_2' => '20,21',
                    ],
                ]],
            ]],
        ];
        $busy = [
            'lunes_2' => [8, 9, 10, 11, 12],
            'martes_2' => [],
        ];
        $out = AgendaWeeklyOccupancyService::attachBusyToWeeklySchedulerUi($ui, $busy, 'hint-ocupado');
        $field = $out['blocks'][0]['fields'][0];
        $this->assertSame('8,9,10,11,12', $field['props']['busy']['lunes_2']);
        $this->assertArrayNotHasKey('lunes_2', $field['initial_values']);
        $this->assertSame('20,21', $field['initial_values']['martes_2']);
        $this->assertSame('hint-ocupado', $field['hint']);
    }
}
