<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Weekly Progress Report (PSR) form definition
    |---------------------------------------------------------------------------
    |
    | Single source of truth for the site checklist and the number of issue /
    | action-plan rows. It drives the submission form, the report view, and the
    | detailed import template (whose status columns become dropdowns), so
    | adding an item here flows through to all three. Entries flagged as a
    | section are group headings and carry no answer of their own.
    |
    */

    'checklist' => [
        ['seq' => '1.0', 'label' => 'General Site Conditions', 'section' => true],
        ['seq' => '1.1', 'label' => 'Site access is clear, secure, and signposted'],
        ['seq' => '1.2', 'label' => 'Appropriate signage (directional, hazard, information) is posted'],
        ['seq' => '2.0', 'label' => 'Quality Assurance and Control', 'section' => true],
        ['seq' => '2.1', 'label' => 'Approved updated drawings and specifications available on-site'],
        ['seq' => '2.2', 'label' => 'Workmanship (Civil, Electrical, Mechanical) meets standards'],
    ],

    'issue_rows' => 3,

    // Allowed checklist answers, offered as a dropdown in the form and template.
    'statuses' => ['√', '✕', 'Ø'],

];
