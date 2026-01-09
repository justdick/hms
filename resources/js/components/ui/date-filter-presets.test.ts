import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { calculateDateRange } from './date-filter-presets';

describe('calculateDateRange', () => {
    // Mock the current date to ensure consistent test results
    const mockDate = new Date('2026-01-09T12:00:00.000Z'); // Friday, January 9, 2026

    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(mockDate);
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('returns today\'s date for "today" preset', () => {
        const result = calculateDateRange('today');
        expect(result).toEqual({
            from: '2026-01-09',
            to: '2026-01-09',
        });
    });

    it('returns yesterday\'s date for "yesterday" preset', () => {
        const result = calculateDateRange('yesterday');
        expect(result).toEqual({
            from: '2026-01-08',
            to: '2026-01-08',
        });
    });

    it('returns this week\'s range for "this_week" preset (Sunday to today)', () => {
        // January 9, 2026 is a Friday
        // Week starts on Sunday, January 4, 2026
        const result = calculateDateRange('this_week');
        expect(result).toEqual({
            from: '2026-01-04',
            to: '2026-01-09',
        });
    });

    it('returns last week\'s range for "last_week" preset (Sunday to Saturday)', () => {
        // Last week: December 28, 2025 (Sunday) to January 3, 2026 (Saturday)
        const result = calculateDateRange('last_week');
        expect(result).toEqual({
            from: '2025-12-28',
            to: '2026-01-03',
        });
    });

    it('returns this month\'s range for "this_month" preset', () => {
        const result = calculateDateRange('this_month');
        expect(result).toEqual({
            from: '2026-01-01',
            to: '2026-01-09',
        });
    });

    it('returns last month\'s range for "last_month" preset', () => {
        const result = calculateDateRange('last_month');
        expect(result).toEqual({
            from: '2025-12-01',
            to: '2025-12-31',
        });
    });

    it('returns empty strings for unknown preset', () => {
        const result = calculateDateRange('unknown');
        expect(result).toEqual({
            from: '',
            to: '',
        });
    });

    it('returns empty strings for empty string preset', () => {
        const result = calculateDateRange('');
        expect(result).toEqual({
            from: '',
            to: '',
        });
    });
});

describe('calculateDateRange edge cases', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('handles first day of month correctly for "this_month"', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-02-01T12:00:00.000Z'));
        
        const result = calculateDateRange('this_month');
        expect(result).toEqual({
            from: '2026-02-01',
            to: '2026-02-01',
        });
    });

    it('handles last day of month correctly for "last_month"', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-03-15T12:00:00.000Z'));
        
        const result = calculateDateRange('last_month');
        // February 2026 has 28 days (not a leap year)
        expect(result).toEqual({
            from: '2026-02-01',
            to: '2026-02-28',
        });
    });

    it('handles Sunday correctly for "this_week"', () => {
        vi.useFakeTimers();
        // January 4, 2026 is a Sunday
        vi.setSystemTime(new Date('2026-01-04T12:00:00.000Z'));
        
        const result = calculateDateRange('this_week');
        expect(result).toEqual({
            from: '2026-01-04',
            to: '2026-01-04',
        });
    });

    it('handles year boundary for "last_week"', () => {
        vi.useFakeTimers();
        // January 3, 2026 is a Saturday
        vi.setSystemTime(new Date('2026-01-03T12:00:00.000Z'));
        
        const result = calculateDateRange('last_week');
        // Last week spans December 21-27, 2025
        expect(result).toEqual({
            from: '2025-12-21',
            to: '2025-12-27',
        });
    });
});
