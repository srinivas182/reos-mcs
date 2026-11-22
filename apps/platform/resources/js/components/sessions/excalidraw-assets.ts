/**
 * Excalidraw loads its fonts from REOS (copied to /vendor/excalidraw by the
 * build), never from a CDN. Set before Excalidraw is imported.
 */
declare global {
    interface Window {
        EXCALIDRAW_ASSET_PATH?: string;
    }
}

if (typeof window !== 'undefined') {
    window.EXCALIDRAW_ASSET_PATH = '/vendor/excalidraw/';
}

export {};
