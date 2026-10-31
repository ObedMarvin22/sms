<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The last invoice number used in a year, across all schools (platform model). Locked while a
 * number is taken (NextSaasInvoiceNumber).
 *
 * @property int $id
 * @property int $year
 * @property int $last_number
 */
class SaasInvoiceSequence extends Model
{
    /** @var list<string> */
    protected $fillable = ['year', 'last_number'];
}
