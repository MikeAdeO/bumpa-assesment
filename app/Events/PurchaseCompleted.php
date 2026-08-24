<?php

namespace App\Events;

use App\Models\Purchase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * Create the event with the completed purchase that triggered it.
     */
    public function __construct(
        public readonly Purchase $purchase
    ) {
        //
    }
}
