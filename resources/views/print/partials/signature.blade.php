{{--
    One signature block.

    `approval` — ['actor' => ?string, 'date' => ?string, 'word' => ?string] —
    stamps the block as already signed, so a printout of a fully approved
    document carries its own proof rather than needing a wet signature. `word`
    is what the stamp says, APPROVED unless the block means something else
    (REVIEWED, NOTED). The signatory's own name, where the system knows who
    signed, beats the configured placeholder for that office.
--}}
@php
    $approval = $approval ?? null;
    $printed  = ($approval['actor'] ?? null) ?: ($name ?? '');
@endphp
<div class="cell{{ $approval ? ' stamped' : '' }}">
    <div class="role">{{ $role }}</div>
    @if($approval)
        <div class="stamp">
            <div class="word">{{ $approval['word'] ?? 'APPROVED' }}</div>
            @if(!empty($approval['date']))
                <div class="when">{{ $approval['date'] }}</div>
            @endif
        </div>
    @endif
    <div class="name">{{ $printed ?: ' ' }}</div>
    <div class="line">{{ $title }}</div>
</div>
