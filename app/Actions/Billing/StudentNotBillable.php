<?php

namespace App\Actions\Billing;

use RuntimeException;

/**
 * Why a student gets no bill this time (no fee structure, not enrolled, nothing to charge).
 * Billing runs list these instead of failing.
 */
class StudentNotBillable extends RuntimeException {}
