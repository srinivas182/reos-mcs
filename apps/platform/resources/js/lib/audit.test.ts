import { describe, expect, it } from 'vitest';
import { describeChange } from '@/lib/audit';

describe('describeChange', () => {
    it('shows scalars and changed fields only', () => {
        expect(describeChange('active', 'suspended')).toBe(
            'active → suspended',
        );
        expect(
            describeChange(
                { plan: 'brokerage', seats: 25, region: 'us' },
                { plan: 'enterprise', seats: 142, region: 'us' },
            ),
        ).toBe('plan: brokerage → enterprise; seats: 25 → 142');
        expect(describeChange(null, { status: 'revoked' })).toBe(
            'status: — → revoked',
        );
        expect(describeChange(null, null)).toBe('');
    });
});
