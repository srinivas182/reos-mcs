/**
 * How whiteboard changes travel between the people in a room (B6.3b): the
 * LiveKit data channel in a real room, nothing in a simulated one.
 */
export type BoardChannel = {
    send: (elements: readonly unknown[]) => void;
    /** Calls back with elements from others; returns a function that stops. */
    receive: (onElements: (elements: unknown[]) => void) => () => void;
};

const encoder = new TextEncoder();
const decoder = new TextDecoder();

export function encodeBoard(elements: readonly unknown[]): Uint8Array {
    return encoder.encode(JSON.stringify({ t: 'els', els: elements }));
}

export function decodeBoard(payload: Uint8Array): unknown[] | null {
    try {
        const message = JSON.parse(decoder.decode(payload)) as {
            t?: string;
            els?: unknown;
        };

        return message.t === 'els' && Array.isArray(message.els)
            ? message.els
            : null;
    } catch {
        return null;
    }
}
