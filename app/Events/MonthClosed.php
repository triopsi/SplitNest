<?php

namespace App\Events;

use App\Models\MonthlyClosing;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched (after commit) when a month has been closed successfully.
 */
class MonthClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * Create the event for the given closing.
     */
    public function __construct(public MonthlyClosing $closing) {}
}
