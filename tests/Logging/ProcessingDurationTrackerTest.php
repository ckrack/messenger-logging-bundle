<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\Logging;

use C10k\MessengerLoggingBundle\Logging\ProcessingDurationTracker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ProcessingDurationTracker::class)]
final class ProcessingDurationTrackerTest extends TestCase
{
    public function testItReturnsElapsedMillisecondsAndRemovesEntry(): void
    {
        $clock = new MockClock('2024-04-23 17:41:32 UTC');
        $tracker = new ProcessingDurationTracker($clock);

        $tracker->start('message-1');
        $clock->sleep(1.25);

        self::assertSame(1250, $tracker->stopMs('message-1'));
        self::assertNull($tracker->stopMs('message-1'));
    }

    public function testItReturnsNullWithoutStart(): void
    {
        $tracker = new ProcessingDurationTracker(new MockClock('2024-04-23 17:41:32 UTC'));

        self::assertNull($tracker->stopMs('missing'));
    }

    public function testItTracksInterleavedUuidsIndependently(): void
    {
        $clock = new MockClock('2024-04-23 17:41:32 UTC');
        $tracker = new ProcessingDurationTracker($clock);

        $tracker->start('message-a');
        $clock->sleep(0.1);
        $tracker->start('message-b');
        $clock->sleep(0.2);

        self::assertSame(300, $tracker->stopMs('message-a'));

        $clock->sleep(0.3);

        self::assertSame(500, $tracker->stopMs('message-b'));
    }

    public function testItEvictsTheOldestEntryWhenCapacityIsExceeded(): void
    {
        $tracker = new ProcessingDurationTracker(new MockClock('2024-04-23 17:41:32 UTC'));

        for ($i = 0; $i <= 1000; ++$i) {
            $tracker->start('message-'.$i);
        }

        self::assertNull($tracker->stopMs('message-0'));
        self::assertSame(0, $tracker->stopMs('message-1000'));
    }

    public function testRestartingSameUuidOverwritesTheTimestamp(): void
    {
        $clock = new MockClock('2024-04-23 17:41:32 UTC');
        $tracker = new ProcessingDurationTracker($clock);

        $tracker->start('message-1');
        $clock->sleep(5);
        $tracker->start('message-1');
        $clock->sleep(2.25);

        self::assertSame(2250, $tracker->stopMs('message-1'));
    }
}
