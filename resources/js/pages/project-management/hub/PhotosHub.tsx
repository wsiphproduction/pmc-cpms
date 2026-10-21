import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionBtns, DataTable, Field, HubProject, HubShell, InfoStrip, Modal, SubTag, inputStyle } from './Common';
import { useConfirm } from '@/components/useConfirm';

interface Photo { id: number; filename: string; url: string; uploaded_by: string }
interface AlbumRow { id: number; photo_date: string; uploaded_by: string; photos: Photo[]; sub_project_id: number | null; sub_project_no: string | null }

const TONE = '#7c3aed';

/** Photos are sent this many to a request, so a big selection never meets the host's post size limit. */
const BATCH_SIZE = 5;
/** Longest edge a photo is scaled down to before upload — plenty for site records, a fraction of a phone original. */
const MAX_EDGE = 2560;

const xsrfToken = () =>
    decodeURIComponent(document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

/**
 * Scale a photo down to MAX_EDGE and re-encode it as JPEG. Anything the browser
 * cannot decode (or a GIF, which may be animated) goes up as it is, and so does
 * a photo the re-encode would not make smaller.
 */
async function shrink(file: File): Promise<File> {
    if (file.type === 'image/gif') return file;
    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        const ctx = canvas.getContext('2d')!;
        // A transparent PNG would otherwise turn black as a JPEG.
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();
        const blob = await new Promise<Blob | null>(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.85));
        if (!blob || blob.size >= file.size) return file;
        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
    } catch {
        return file;
    }
}

export default function PhotosHub({ project, albums, canEdit = true }: { project: HubProject; albums: AlbumRow[]; canEdit?: boolean }) {
    const [photoDate, setPhotoDate] = useState('');
    const [files, setFiles]         = useState<File[]>([]);
    const [progress, setProgress]   = useState<{ done: number; total: number } | null>(null);
    const [error, setError]         = useState('');
    const [dragOver, setDragOver]   = useState(false);
    const [galleryId, setGalleryId] = useState<number | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    // Read the album from props each render, so a photo deleted in the gallery
    // drops out as soon as the page reloads, and a deleted album closes it.
    const gallery = albums.find(a => a.id === galleryId) ?? null;

    const previews = useMemo(() => files.map(f => URL.createObjectURL(f)), [files]);
    useEffect(() => () => previews.forEach(u => URL.revokeObjectURL(u)), [previews]);

    const addFiles = (list: FileList | null) => {
        const images = Array.from(list ?? []).filter(f => f.type.startsWith('image/'));
        if (images.length) setFiles(prev => [...prev, ...images]);
        if (fileRef.current) fileRef.current.value = '';
    };

    const uploading = progress !== null;

    const handleUpload = async () => {
        if (!photoDate) { setError('Please select the date the photos were taken.'); return; }
        if (!files.length) { setError('Please choose at least one photo.'); return; }
        setError('');
        setProgress({ done: 0, total: files.length });

        let done = 0;
        try {
            for (let i = 0; i < files.length; i += BATCH_SIZE) {
                const batch = await Promise.all(files.slice(i, i + BATCH_SIZE).map(shrink));
                const fd = new FormData();
                fd.append('photo_date', photoDate);
                batch.forEach(f => fd.append('photos[]', f));

                const res = await fetch(route('hub.photos.store', project.id), {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-XSRF-TOKEN': xsrfToken(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) {
                    const body = await res.json().catch(() => null);
                    const first = body?.errors ? Object.values(body.errors as Record<string, string[]>)[0]?.[0] : null;
                    throw new Error(first ?? body?.message ?? (res.status === 413 ? 'The photos are too large to upload.' : `Upload failed (${res.status}).`));
                }
                done += batch.length;
                setProgress({ done, total: files.length });
            }
            setFiles([]);
            setPhotoDate('');
        } catch (e) {
            // Batches already sent are saved, so only the rest stay selected for a retry.
            setFiles(prev => prev.slice(done));
            setError(`${e instanceof Error ? e.message : 'Upload failed.'}${done ? ` ${done} photo(s) were saved; the rest are still selected.` : ''}`);
        } finally {
            setProgress(null);
            router.reload({ only: ['hub_data', 'hub_counts'] });
        }
    };

    const hasSubRows = albums.some(a => !!a.sub_project_id);

    const { confirm: showConfirm, dialog: confirmDialog } = useConfirm();

    const deleteAlbum = (album: AlbumRow) => {
        showConfirm(`Delete all ${album.photos.length} photo(s) from ${album.photo_date}?`, () => {
            router.delete(route('hub.photos.destroy', [project.id, album.id]), { preserveScroll: true });
        }, { title: 'Delete Photos', confirmLabel: 'Delete', variant: 'danger' });
    };

    const deletePhoto = (album: AlbumRow, photo: Photo) => {
        showConfirm(`Delete "${photo.filename}"?`, () => {
            router.delete(route('hub.photos.destroy-photo', [project.id, album.id, photo.id]), { preserveScroll: true });
        }, { title: 'Delete Photo', confirmLabel: 'Delete', variant: 'danger' });
    };

    return (
        <HubShell>
            {confirmDialog}
            <InfoStrip project={project} accent={TONE} />

            {canEdit && (
                <div
                    onDragOver={e => { e.preventDefault(); setDragOver(true); }}
                    onDragLeave={() => setDragOver(false)}
                    onDrop={e => { e.preventDefault(); setDragOver(false); if (!uploading) addFiles(e.dataTransfer.files); }}
                    style={{ background: dragOver ? '#ede9fe' : '#f5f3ff', border: `2px dashed ${dragOver ? TONE : '#ddd6fe'}`, borderRadius: '12px', padding: '18px', marginBottom: '22px', transition: 'background 0.12s' }}
                >
                    <div style={{ display: 'grid', gridTemplateColumns: '200px 1fr 170px', gap: '12px', alignItems: 'start' }}>
                        <Field label="Photo Date">
                            <input type="date" style={inputStyle} value={photoDate} disabled={uploading} onChange={e => setPhotoDate(e.target.value)} />
                        </Field>
                        <Field label="Photos (choose several, or drop them here)">
                            <input type="file" ref={fileRef} accept="image/*" multiple disabled={uploading} style={inputStyle} onChange={e => addFiles(e.target.files)} />
                        </Field>
                        <div>
                            <div style={{ fontSize: '11px', fontWeight: 800, color: '#374151', marginBottom: '5px' }}>&nbsp;</div>
                            <button type="button" onClick={handleUpload} disabled={uploading} style={{ width: '100%', padding: '8px 13px', borderRadius: '7px', background: TONE, color: '#fff', border: 'none', fontWeight: 800, cursor: uploading ? 'wait' : 'pointer', fontSize: '12.5px' }}>
                                {progress ? `Uploading ${progress.done}/${progress.total}…` :`Upload${files.length ? ` ${files.length}` : ''} Photo${files.length === 1 ? '' : 's'}`}
                            </button>
                        </div>
                    </div>

                    {files.length > 0 && (
                        <div style={{ marginTop: '14px' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                <span style={{ fontSize: '11.5px', fontWeight: 700, color: '#5b21b6' }}>{files.length} photo{files.length === 1 ? '' : 's'} selected</span>
                                {!uploading && (
                                    <button type="button" onClick={() => setFiles([])} style={{ border: 'none', background: 'none', color: '#64748b', fontSize: '11.5px', fontWeight: 600, cursor: 'pointer', padding: 0 }}>Clear all</button>
                                )}
                            </div>
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
                                {previews.map((src, i) => (
                                    <div key={src} style={{ position: 'relative', width: '72px', height: '72px', borderRadius: '8px', overflow: 'hidden', border: '1px solid #ddd6fe', background: '#fff' }}>
                                        <img src={src} alt={files[i]?.name} style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block' }} />
                                        {!uploading && (
                                            <button
                                                type="button"
                                                title="Remove"
                                                onClick={() => setFiles(prev => prev.filter((_, j) => j !== i))}
                                                style={{ position: 'absolute', top: '3px', right: '3px', width: '20px', height: '20px', borderRadius: '50%', border: 'none', background: 'rgba(15,23,42,0.7)', color: '#fff', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 0 }}
                                            >
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div style={{ fontSize: '11.5px', color: '#7c3aed', marginTop: '10px' }}>
                        Uploading on a date that already has photos adds these to it.
                    </div>
                    {error && <div style={{ padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '7px', color: '#dc2626', fontSize: '12.5px', fontWeight: 600, marginTop: '10px' }}>{error}</div>}
                </div>
            )}

            {albums.length === 0 ? (
                <div style={{ padding: '40px', textAlign: 'center', color: '#94a3b8', fontSize: '13px', border: '1px dashed #e2e8f0', borderRadius: '8px' }}>
                    No photos uploaded yet.
                </div>
            ) : (
                <DataTable
                    headers={['Seq#', ...(hasSubRows ? ['Project'] : []), 'Photo Date', 'Preview', 'Photos', 'Uploaded By', 'Actions']}
                    rows={albums.map((album, idx) => [
                        <span style={{ color: '#94a3b8' }}>{idx + 1}</span>,
                        ...(hasSubRows ? [<SubTag no={album.sub_project_no} />] : []),
                        <strong>{album.photo_date}</strong>,
                        <ThumbStrip album={album} onOpen={() => setGalleryId(album.id)} />,
                        <span style={{ fontWeight: 700, color: '#5b21b6' }}>{album.photos.length}</span>,
                        <span style={{ fontSize: '12.5px' }}>{album.uploaded_by}</span>,
                        <ActionBtns
                            view
                            del={canEdit && !album.sub_project_id}
                            open={!!album.sub_project_id}
                            onView={() => setGalleryId(album.id)}
                            onDelete={() => deleteAlbum(album)}
                            onOpen={() => router.visit(route('projects.hub.photos', album.sub_project_id!))}
                        />,
                    ])}
                />
            )}

            {gallery && (
                <Gallery
                    album={gallery}
                    onClose={() => setGalleryId(null)}
                    onDelete={canEdit && !gallery.sub_project_id ? photo => deletePhoto(gallery, photo) : undefined}
                />
            )}
        </HubShell>
    );
}

function ThumbStrip({ album, onOpen }: { album: AlbumRow; onOpen: () => void }) {
    const shown = album.photos.slice(0, 4);
    const extra = album.photos.length - shown.length;
    return (
        <button type="button" onClick={onOpen} title="View photos" style={{ display: 'flex', gap: '4px', padding: 0, border: 'none', background: 'none', cursor: 'pointer' }}>
            {shown.map(p => (
                <img key={p.id} src={p.url} alt={p.filename} loading="lazy" style={{ width: '44px', height: '44px', objectFit: 'cover', borderRadius: '6px', border: '1px solid #e2e8f0', display: 'block' }} />
            ))}
            {extra > 0 && (
                <span style={{ width: '44px', height: '44px', borderRadius: '6px', background: '#f5f3ff', border: '1px solid #ddd6fe', color: '#5b21b6', fontSize: '12px', fontWeight: 800, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>
                    +{extra}
                </span>
            )}
        </button>
    );
}

function Gallery({ album, onClose, onDelete }: { album: AlbumRow; onClose: () => void; onDelete?: (photo: Photo) => void }) {
    const [index, setIndex] = useState<number | null>(null);

    // Keep the lightbox on a real photo when one is deleted out from under it.
    const count = album.photos.length;
    const current = index === null || count === 0 ? null : album.photos[Math.min(index, count - 1)];

    return (
        <Modal title={`Photos — ${album.photo_date}${album.sub_project_no ? ` (${album.sub_project_no})` : ''}`} onClose={onClose} headerBg="#4c1d95" size="1100px">
            <div style={{ fontSize: '12px', color: '#64748b', marginBottom: '14px' }}>
                {count} photo{count === 1 ? '' : 's'} · click a photo to view it full size
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(190px, 1fr))', gap: '10px' }}>
                {album.photos.map((photo, i) => (
                    <div key={photo.id} className="pmc-gallery-tile" style={{ position: 'relative', aspectRatio: '4 / 3', borderRadius: '10px', overflow: 'hidden', background: '#f1f5f9', border: '1px solid #e2e8f0' }}>
                        <button type="button" onClick={() => setIndex(i)} title={photo.filename} style={{ display: 'block', width: '100%', height: '100%', padding: 0, border: 'none', cursor: 'zoom-in', background: 'none' }}>
                            <img src={photo.url} alt={photo.filename} loading="lazy" style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block', transition: 'transform 0.2s' }} />
                        </button>
                        <div className="pmc-gallery-caption" style={{ position: 'absolute', left: 0, right: 0, bottom: 0, padding: '18px 8px 6px', background: 'linear-gradient(transparent, rgba(15,23,42,0.75))', color: '#fff', fontSize: '11px', fontWeight: 600, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', pointerEvents: 'none' }}>
                            {photo.filename}
                        </div>
                        {onDelete && (
                            <button
                                type="button"
                                title="Delete photo"
                                className="pmc-gallery-delete"
                                onClick={() => onDelete(photo)}
                                style={{ position: 'absolute', top: '6px', right: '6px', width: '28px', height: '28px', borderRadius: '6px', border: 'none', background: 'rgba(220,38,38,0.9)', color: '#fff', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 0 }}
                            >
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                            </button>
                        )}
                    </div>
                ))}
            </div>
            <style>{`
                .pmc-gallery-tile:hover img { transform: scale(1.04); }
                .pmc-gallery-tile .pmc-gallery-delete { opacity: 0; transition: opacity 0.15s; }
                .pmc-gallery-tile:hover .pmc-gallery-delete, .pmc-gallery-delete:focus-visible { opacity: 1; }
            `}</style>

            {current && (
                <Lightbox
                    photos={album.photos}
                    index={Math.min(index!, count - 1)}
                    onIndex={setIndex}
                    onClose={() => setIndex(null)}
                />
            )}
        </Modal>
    );
}

function Lightbox({ photos, index, onIndex, onClose }: { photos: Photo[]; index: number; onIndex: (i: number) => void; onClose: () => void }) {
    const photo = photos[index];
    const count = photos.length;
    const prev = () => onIndex((index - 1 + count) % count);
    const next = () => onIndex((index + 1) % count);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') { e.stopPropagation(); onClose(); }
            else if (e.key === 'ArrowLeft') prev();
            else if (e.key === 'ArrowRight') next();
        };
        window.addEventListener('keydown', onKey, true);
        return () => window.removeEventListener('keydown', onKey, true);
    });

    const navBtn = (side: 'left' | 'right', onClick: () => void) => (
        <button
            type="button"
            onClick={e => { e.stopPropagation(); onClick(); }}
            title={side === 'left' ? 'Previous (←)' : 'Next (→)'}
            style={{ position: 'absolute', top: '50%', [side]: '18px', transform: 'translateY(-50%)', width: '46px', height: '46px', borderRadius: '50%', border: '1px solid rgba(255,255,255,0.25)', background: 'rgba(255,255,255,0.1)', color: '#fff', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
        >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                {side === 'left' ? <polyline points="15 18 9 12 15 6"/> : <polyline points="9 18 15 12 9 6"/>}
            </svg>
        </button>
    );

    return (
        <div onClick={onClose} style={{ position: 'fixed', inset: 0, zIndex: 600, background: 'rgba(2,6,23,0.94)', display: 'flex', flexDirection: 'column' }}>
            {/* Top bar */}
            <div onClick={e => e.stopPropagation()} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px', padding: '12px 18px', color: '#e2e8f0' }}>
                <div style={{ fontSize: '13px', fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    <span style={{ color: '#a78bfa', fontWeight: 800, marginRight: '10px' }}>{index + 1} / {count}</span>
                    {photo.filename}
                    <span style={{ color: '#94a3b8', fontWeight: 500, marginLeft: '10px' }}>· {photo.uploaded_by}</span>
                </div>
                <div style={{ display: 'flex', gap: '8px', flexShrink: 0 }}>
                    <a href={photo.url} download={photo.filename} title="Download" style={{ width: '34px', height: '34px', borderRadius: '8px', border: '1px solid rgba(255,255,255,0.25)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </a>
                    <button type="button" onClick={onClose} title="Close (Esc)" style={{ width: '34px', height: '34px', borderRadius: '8px', border: '1px solid rgba(255,255,255,0.25)', background: 'transparent', color: '#fff', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>

            {/* Photo */}
            <div style={{ position: 'relative', flex: 1, minHeight: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 80px' }}>
                <img
                    key={photo.id}
                    src={photo.url}
                    alt={photo.filename}
                    onClick={e => e.stopPropagation()}
                    style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain', borderRadius: '4px', boxShadow: '0 10px 40px rgba(0,0,0,0.5)' }}
                />
                {count > 1 && navBtn('left', prev)}
                {count > 1 && navBtn('right', next)}
            </div>

            {/* Filmstrip */}
            {count > 1 && (
                <div onClick={e => e.stopPropagation()} style={{ display: 'flex', gap: '6px', padding: '12px 18px', overflowX: 'auto', justifyContent: count > 12 ? 'flex-start' : 'center' }}>
                    {photos.map((p, i) => (
                        <button
                            key={p.id}
                            type="button"
                            onClick={() => onIndex(i)}
                            style={{ flexShrink: 0, padding: 0, width: '64px', height: '48px', borderRadius: '5px', overflow: 'hidden', cursor: 'pointer', border: `2px solid ${i === index ? '#a78bfa' : 'transparent'}`, opacity: i === index ? 1 : 0.55, background: 'none' }}
                        >
                            <img src={p.url} alt={p.filename} loading="lazy" style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block' }} />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
