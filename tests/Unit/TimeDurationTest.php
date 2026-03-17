<?php

use App\Support\TimeDuration;

describe('toMinutes', function (): void {
    it('parses minutes', function (): void {
        expect(TimeDuration::toMinutes('30m'))->toBe(30)
            ->and(TimeDuration::toMinutes('45m'))->toBe(45);
    });

    it('parses hours', function (): void {
        expect(TimeDuration::toMinutes('2h'))->toBe(120)
            ->and(TimeDuration::toMinutes('1h'))->toBe(60);
    });

    it('parses days as 8 hours', function (): void {
        expect(TimeDuration::toMinutes('1d'))->toBe(480)
            ->and(TimeDuration::toMinutes('2d'))->toBe(960);
    });

    it('parses weeks as 40 hours', function (): void {
        expect(TimeDuration::toMinutes('1w'))->toBe(2400);
    });

    it('parses combined formats', function (): void {
        expect(TimeDuration::toMinutes('2h30m'))->toBe(150)
            ->and(TimeDuration::toMinutes('1d 2h 30m'))->toBe(630)
            ->and(TimeDuration::toMinutes('1d4h'))->toBe(720);
    });

    it('accepts plain numbers as minutes', function (): void {
        expect(TimeDuration::toMinutes('90'))->toBe(90);
    });

    it('returns null for empty or invalid input', function (): void {
        expect(TimeDuration::toMinutes(''))->toBeNull()
            ->and(TimeDuration::toMinutes('abc'))->toBeNull();
    });
});

describe('format', function (): void {
    it('formats minutes only', function (): void {
        expect(TimeDuration::format(30))->toBe('30m')
            ->and(TimeDuration::format(45))->toBe('45m');
    });

    it('formats hours', function (): void {
        expect(TimeDuration::format(60))->toBe('1h')
            ->and(TimeDuration::format(120))->toBe('2h');
    });

    it('formats hours and minutes', function (): void {
        expect(TimeDuration::format(150))->toBe('2h 30m');
    });

    it('formats days', function (): void {
        expect(TimeDuration::format(480))->toBe('1d')
            ->and(TimeDuration::format(960))->toBe('2d');
    });

    it('formats complex durations', function (): void {
        expect(TimeDuration::format(630))->toBe('1d 2h 30m');
    });

    it('formats weeks', function (): void {
        expect(TimeDuration::format(2400))->toBe('1w')
            ->and(TimeDuration::format(2520))->toBe('1w 2h');
    });

    it('returns null for null or zero', function (): void {
        expect(TimeDuration::format(null))->toBeNull()
            ->and(TimeDuration::format(0))->toBeNull();
    });
});
