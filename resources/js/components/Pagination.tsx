import { router } from '@inertiajs/react';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/** Laravel's LengthAwarePaginator, as Inertia receives it. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

/**
 * Page links under a server-paged table. Renders nothing for a single page.
 * Keeps the scroll position, so moving pages doesn't jump to the top.
 */
export default function Pagination({ data }: { data: Paginated<unknown> }) {
    if (data.last_page <= 1) return null;

    return (
        <div style={{ padding: '14px 18px', borderTop: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '10px' }}>
            <span style={{ fontSize: '12.5px', color: '#9ca3af' }}>
                Showing <strong style={{ color: '#374151' }}>{data.from}–{data.to}</strong> of <strong style={{ color: '#374151' }}>{data.total}</strong> entries
            </span>
            <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap' }}>
                {data.links.map((link, i) => {
                    const isDisabled = !link.url;
                    const label = link.label
                        .replace('&laquo;', '‹')
                        .replace('&raquo;', '›')
                        .replace(/&[^;]+;/g, '');
                    return (
                        <button
                            key={i}
                            type="button"
                            disabled={isDisabled}
                            onClick={() => link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })}
                            style={{
                                minWidth: '32px', height: '32px', padding: '0 8px',
                                borderRadius: '7px', border: '1px solid',
                                borderColor: link.active ? '#2563eb' : '#e5e7eb',
                                background: link.active ? '#2563eb' : '#fff',
                                color: link.active ? '#fff' : isDisabled ? '#d1d5db' : '#374151',
                                fontSize: '12.5px', fontWeight: link.active ? 700 : 400,
                                cursor: isDisabled ? 'not-allowed' : 'pointer', transition: 'all 0.12s',
                            }}
                        >
                            {label}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
