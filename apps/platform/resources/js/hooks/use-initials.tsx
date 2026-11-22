import { useCallback } from 'react';

import { nameParts } from '@/lib/person-name';

export type GetInitialsFn = (fullName: string | null | undefined) => string;

function getInitial(name: string): string {
    return Array.from(name)[0] ?? '';
}

export function useInitials(): GetInitialsFn {
    return useCallback((fullName: string | null | undefined): string => {
        // "Dr. Rachel Hughes" → "RH".
        const names = nameParts(fullName);

        if (names.length === 0) {
            return '';
        }

        if (names.length === 1) {
            return getInitial(names[0]).toUpperCase();
        }

        const firstInitial = getInitial(names[0]);
        const lastInitial = getInitial(names[names.length - 1]);

        return `${firstInitial}${lastInitial}`.toUpperCase();
    }, []);
}
