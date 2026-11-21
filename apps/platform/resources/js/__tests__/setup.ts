import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

afterEach(() => cleanup());

// jsdom lacks the pointer-capture and scrolling calls Radix Select makes when it opens.
const missing: Record<string, () => unknown> = {
    hasPointerCapture: () => false,
    setPointerCapture: () => undefined,
    releasePointerCapture: () => undefined,
    scrollIntoView: () => undefined,
};

for (const [name, value] of Object.entries(missing)) {
    if (!(name in Element.prototype)) {
        Object.defineProperty(Element.prototype, name, {
            value,
            configurable: true,
            writable: true,
        });
    }
}
