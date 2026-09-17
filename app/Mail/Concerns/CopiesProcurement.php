<?php

namespace App\Mail\Concerns;

/**
 * The RFQ and NTP mails all carry the same standing copy to procurement.
 * The addresses come from config (MAIL_PROCUREMENT_CC), not from the send
 * forms, so the people filling those in neither see nor can drop them.
 */
trait CopiesProcurement
{
    /** @return array<int, string> */
    public static function procurementCc(): array
    {
        return collect(explode(',', (string) config('mail.procurement_cc')))
            ->map(fn (string $address) => trim($address))
            ->filter(fn (string $address) => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)
            ->values()
            ->all();
    }
}
