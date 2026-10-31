<?php

use App\Models\Activity;
use App\Models\Concerns\BelongsToSchool;
use App\Models\PaystackEvent;
use App\Models\Plan;
use App\Models\SaasInvoice;
use App\Models\SaasInvoiceSequence;
use App\Models\SaasPayment;
use App\Models\School;
use App\Models\User;

/*
| Platform models have no school_id (or a nullable one) and are listed here on purpose.
| Every other model in App\Models is school-owned and must use BelongsToSchool.
*/
const PLATFORM_MODELS = [
    School::class,
    // Nullable school_id (null = Orange Tree staff) with its own login scoping: Sprint 1 step 5.
    User::class,
    // Nullable school_id (platform events); has SchoolScope and fills school_id itself.
    Activity::class,
    // Paystack webhook events: Paystack does not know our schools; the school comes from our
    // own transaction reference when the event is processed (Sprint 4 step 4).
    PaystackEvent::class,
    // Orange Tree's billing of schools (Sprint 4 step 9): read only by the console. Invoices and
    // payments carry school_id but no school scope.
    Plan::class,
    SaasInvoice::class,
    SaasInvoiceSequence::class,
    SaasPayment::class,
];

arch('school-owned models use BelongsToSchool')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(BelongsToSchool::class)
    ->ignoring([...PLATFORM_MODELS, 'App\Models\Concerns']);
