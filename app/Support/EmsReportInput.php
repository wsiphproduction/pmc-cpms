<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\EmsSurveyReport;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What the EMS Survey Report forms send: the header and assessment (the
 * engineer's pop-up on the project hub), MEPEO's finding (the
 * representative's MEPEO page) and the supporting documents both may add.
 */
class EmsReportInput
{
    public const MAX_FILE_KB = 20480;

    public const FILE_TYPES = 'pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,ppt,pptx';

    /** The project-side header above the assessment. */
    public static function header(Request $request): array
    {
        return $request->validate([
            'date_requested' => ['required', 'date'],
            'location'       => ['nullable', 'string', 'max:255'],
            'contractor'     => ['nullable', 'string', 'max:255'],
            'target_start'   => ['nullable', 'date'],
            'target_end'     => ['nullable', 'date', 'after_or_equal:target_start'],
        ]);
    }

    /**
     * The engineer's assessment: the checklists and write-ups. Any of it may
     * stay blank while the report is a draft; assessmentErrors() says what
     * must be there before it is submitted for review.
     */
    public static function body(Request $request): array
    {
        $data = $request->validate([
            'major_activities'       => ['nullable', 'array'],
            'major_activities.*'     => ['string', Rule::in(array_keys(config('ems.major_activities')))],
            'major_activities_other' => ['nullable', 'string', 'max:255'],
            'other_scope'            => ['nullable', 'string', 'max:5000'],
            'aspects'                => ['nullable', 'array'],
            'aspects.*'              => ['string', Rule::in(array_keys(config('ems.aspects')))],
            'aspects_other'          => ['nullable', 'string', 'max:255'],
            'impacts'                => ['nullable', 'string', 'max:5000'],
            'mitigation'             => ['nullable', 'string', 'max:5000'],
        ]);

        // A form posted as multipart drops an empty list entirely.
        $data['major_activities'] = array_values(array_unique($data['major_activities'] ?? []));
        $data['aspects'] = array_values(array_unique($data['aspects'] ?? []));

        return $data;
    }

    /** What a saved report still lacks before PMD can review it: at least one activity and aspect (or an "Others"). */
    public static function assessmentErrors(EmsSurveyReport $report): array
    {
        $errors = [];

        if (empty($report->major_activities) && blank($report->major_activities_other)) {
            $errors['major_activities'] = 'Tick at least one major activity, or fill in Others, before submitting.';
        }

        if (empty($report->aspects) && blank($report->aspects_other)) {
            $errors['aspects'] = 'Tick at least one environmental aspect, or fill in Others, before submitting.';
        }

        return $errors;
    }

    /**
     * MEPEO's part — "To be filled-out by MEPEO": the Status and the
     * Remarks/Comments. Verifying the report asks for the Status.
     */
    public static function finding(Request $request, bool $verifying = false): array
    {
        return $request->validate([
            'mepeo_status' => [$verifying ? 'required' : 'nullable', Rule::in(array_keys(config('ems.statuses')))],
            'remarks'      => ['nullable', 'string', 'max:5000'],
        ], [
            'mepeo_status.required' => 'Choose the Status before verifying.',
        ]);
    }

    /**
     * Supporting documents to add (`attachments[]`) and to remove
     * (`removed_attachments[]`, by id). Validated before anything is saved,
     * so a rejected file leaves the report as it was.
     *
     * @return array{files: UploadedFile[], removed: int[]}
     */
    public static function attachments(Request $request): array
    {
        $request->validate([
            'attachments'           => ['nullable', 'array', 'max:20'],
            'attachments.*'         => ['file', 'max:' . self::MAX_FILE_KB, 'mimes:' . self::FILE_TYPES],
            'removed_attachments'   => ['nullable', 'array'],
            'removed_attachments.*' => ['integer'],
        ], [
            'attachments.*.max'   => 'Each supporting document must be 20 MB or smaller.',
            'attachments.*.mimes' => 'Supporting documents must be PDF, image, Word, Excel or PowerPoint files.',
        ]);

        return [
            'files'   => array_values($request->file('attachments') ?? []),
            'removed' => array_map('intval', $request->input('removed_attachments', [])),
        ];
    }

    /** Apply what attachments() validated. Removing one deletes its file too. */
    public static function syncAttachments(EmsSurveyReport $report, array $changes): void
    {
        if ($changes['removed'] !== []) {
            $report->attachments()->whereKey($changes['removed'])->get()
                ->each(fn (Attachment $attachment) => self::purge($attachment));
        }

        foreach ($changes['files'] as $file) {
            $path = $file->store("ems/{$report->id}", 'public');

            $attachment = $report->attachments()->create([
                'filename' => $file->getClientOriginalName(),
                'filepath' => $path,
                'type'     => 'other',
            ]);

            $attachment->recordFileVersion(
                $path,
                $file->getClientOriginalName(),
                mimeType: $file->getMimeType(),
                size: $file->getSize(),
            );
        }
    }

    public static function purge(Attachment $attachment): void
    {
        $attachment->purgeFileVersions();
        Storage::disk('public')->delete($attachment->filepath);
        $attachment->delete();
    }
}
