import { xsrfToken } from '@/components/member/member-api';
import { destroy, store } from '@/routes/profile/avatar';

/**
 * Upload a profile photo for the current user. Throws on failure,
 * resolves with the public URL of the stored avatar.
 */
export async function uploadAvatar(file: File): Promise<string> {
    const body = new FormData();
    body.append('avatar', file);
    const response = await fetch(store().url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body,
    });
    if (!response.ok) {
        throw new Error('upload failed');
    }
    return ((await response.json()) as { avatar: string }).avatar;
}

/**
 * Remove the current user's profile photo. Throws on failure.
 */
export async function removeAvatar(): Promise<void> {
    const response = await fetch(destroy().url, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    });
    if (!response.ok) {
        throw new Error('remove failed');
    }
}
