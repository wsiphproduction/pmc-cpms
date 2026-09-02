<?php

namespace App\Models\Concerns;

use App\Models\FileVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * File version history for a record.
 *
 * A record keeps pointing at its current file exactly as before; this trait
 * writes an extra row per upload so the file can be traced back through v2, v1.
 * Replacing a file never deletes the bytes it replaced — that is the whole
 * point — so only deleting the owning record clears the history.
 *
 * Typical use, wherever a file is stored:
 *
 *     $version = $doc->storeVersionedFile($request->file('file'), "hub/qpp/{$project->id}");
 *     $doc->update(['file_path' => $version->filepath, 'filename' => $version->filename]);
 */
trait HasFileVersions
{
    /** Newest version first. */
    public function fileVersions(): MorphMany
    {
        return $this->morphMany(FileVersion::class, 'versionable')
            ->orderByDesc('version');
    }

    /** History for one file slot, newest first. */
    public function versionsOf(string $collection = 'file'): Collection
    {
        return $this->relationLoaded('fileVersions')
            ? $this->fileVersions->where('collection', $collection)->sortByDesc('version')->values()
            : $this->fileVersions()->where('collection', $collection)->get();
    }

    public function latestFileVersion(string $collection = 'file'): ?FileVersion
    {
        return $this->versionsOf($collection)->first();
    }

    /** The number the next upload into this slot gets — 1 when there is none yet. */
    public function nextFileVersion(string $collection = 'file'): int
    {
        return (int) $this->fileVersions()->where('collection', $collection)->max('version') + 1;
    }

    /**
     * Store an upload on the public disk and log it as the next version.
     * The caller still writes the returned path onto its own record.
     */
    public function storeVersionedFile(
        UploadedFile $file,
        string $directory,
        string $collection = 'file',
        ?string $note = null,
    ): FileVersion {
        $path = $file->store($directory, 'public');

        return $this->recordFileVersion(
            $path,
            $file->getClientOriginalName(),
            $collection,
            $file->getMimeType(),
            $file->getSize(),
            $note,
        );
    }

    /** Log a file that is already on disk as the next version of a slot. */
    public function recordFileVersion(
        string $path,
        ?string $filename = null,
        string $collection = 'file',
        ?string $mimeType = null,
        ?int $size = null,
        ?string $note = null,
    ): FileVersion {
        $version = $this->fileVersions()->create([
            'collection'  => $collection,
            'version'     => $this->nextFileVersion($collection),
            'filename'    => $filename ?: basename($path),
            'filepath'    => $path,
            'mime_type'   => $mimeType,
            'size'        => $size,
            'note'        => $note,
            'uploaded_by' => auth()->id(),
        ]);

        // A freshly written version invalidates anything already loaded.
        $this->unsetRelation('fileVersions');

        return $version;
    }

    /**
     * Drop the whole history and its files. Only for deleting the owning
     * record — replacing a file must leave the old versions readable.
     */
    public function purgeFileVersions(): void
    {
        foreach ($this->fileVersions()->get() as $version) {
            Storage::disk('public')->delete($version->filepath);
        }

        $this->fileVersions()->delete();
    }

    /**
     * History shaped for the front end: `[{ id, version, label, filename, url,
     * uploaded_at, uploaded_by, is_current }]`, newest first.
     */
    public function fileVersionPayload(string $collection = 'file'): array
    {
        $versions = $this->versionsOf($collection);
        $current  = $versions->max('version');

        return $versions->map(fn (FileVersion $v) => [
            'id'          => $v->id,
            'version'     => $v->version,
            'label'       => $v->label,
            'filename'    => $v->filename,
            'url'         => $v->url,
            'size'        => $v->size,
            'note'        => $v->note,
            'uploaded_at' => optional($v->created_at)->toDateTimeString(),
            'uploaded_by' => $v->relationLoaded('uploader') ? optional($v->uploader)->name : null,
            'is_current'  => $v->version === $current,
        ])->all();
    }
}
