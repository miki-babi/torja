import { toBlob } from 'html-to-image';

export type ShareResult = 'shared' | 'downloaded';

async function renderPngBlob(node: HTMLElement): Promise<Blob> {
    const blob = await toBlob(node, { pixelRatio: 3, cacheBust: true });
    if (!blob) {
        throw new Error('Could not render image');
    }
    return blob;
}

function downloadBlob(blob: Blob, filename: string) {
    const url = URL.createObjectURL(blob);
    try {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
    } finally {
        URL.revokeObjectURL(url);
    }
}

/**
 * Render a DOM node to a PNG and hand it to the OS: native share sheet
 * when the device supports sharing files, otherwise a download.
 */
export async function shareNodeAsImage(
    node: HTMLElement,
    filename: string,
    title: string,
): Promise<ShareResult> {
    const blob = await renderPngBlob(node);
    const file = new File([blob], filename, { type: 'image/png' });

    if (navigator.canShare?.({ files: [file] })) {
        await navigator.share({ files: [file], title });
        return 'shared';
    }

    downloadBlob(blob, filename);
    return 'downloaded';
}

/**
 * Render a DOM node to a PNG and save it straight to disk.
 */
export async function downloadNodeAsImage(
    node: HTMLElement,
    filename: string,
): Promise<void> {
    downloadBlob(await renderPngBlob(node), filename);
}
