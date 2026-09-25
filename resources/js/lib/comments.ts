/**
 * Turn a failed comment request into a message the user can act on, instead of
 * the post silently vanishing.
 */
export async function commentError(res: Response): Promise<string> {
    if (res.status === 419) return 'Your session expired. Please refresh the page and try again.';
    if (res.status === 403) return 'You are not allowed to comment on this request.';

    try {
        const body = await res.json();
        const first = body?.errors ? Object.values(body.errors as Record<string, string[]>)[0]?.[0] : null;
        if (first) return first;
        if (body?.message) return body.message;
    } catch {
        // Non-JSON response — fall through to the generic message.
    }

    return 'Your comment could not be posted. Please try again.';
}
