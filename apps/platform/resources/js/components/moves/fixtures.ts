import type { Move } from './move-summary';

/** Maria Garcia moving from Lone Star Realty to Gulf Coast Homes (A14.3). */
export const moveFixture = (over: Partial<Move> = {}): Move => ({
    kind: 'brokerage',
    from: { id: 1, name: 'Lone Star Realty' },
    to: { id: 2, name: 'Gulf Coast Homes' },
    moves: [
        { key: 'licenses', count: 1 },
        { key: 'ce_hours', count: 7 },
        { key: 'certificates', count: 2 },
        { key: 'contacts', count: 214 },
        { key: 'points', count: 1850 },
    ],
    stays: [
        { key: 'deals', count: 9 },
        { key: 'compliance', count: 31 },
    ],
    ends: [
        { key: 'mentor', count: 1 },
        { key: 'approvals', count: 2 },
    ],
    ...over,
});
