import { describe, expect, it } from 'vitest';
import { decodeBoard, encodeBoard } from '@/components/sessions/board-channel';

describe('whiteboard messages (B6.3b)', () => {
    it('round-trips changed elements and ignores anything else', () => {
        const els = [{ id: 'rect-1', version: 3 }];
        expect(decodeBoard(encodeBoard(els))).toEqual(els);
        expect(
            decodeBoard(new TextEncoder().encode('{"t":"chat"}')),
        ).toBeNull();
        expect(decodeBoard(new TextEncoder().encode('not json'))).toBeNull();
    });
});
