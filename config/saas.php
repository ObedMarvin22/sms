<?php

/*
| Orange Tree's billing of schools (SPEC section 8): per active student per term, counted on the
| term's census date. The invoice header and payment details come from .env.
*/

return [
    // Days from issue to the due date.
    'due_days' => (int) env('SAAS_DUE_DAYS', 30),

    'invoice' => [
        'from_name' => env('SAAS_INVOICE_FROM_NAME', 'Orange Tree Technologies'),
        'from_address' => env('SAAS_INVOICE_FROM_ADDRESS', ''),
        'from_email' => env('SAAS_INVOICE_FROM_EMAIL', ''),
        'from_phone' => env('SAAS_INVOICE_FROM_PHONE', ''),
        // e.g. "GCB Bank · Orange Tree Technologies · 1234567890" — shown under "How to pay".
        'payment_details' => env('SAAS_INVOICE_PAYMENT_DETAILS', ''),
    ],
];
