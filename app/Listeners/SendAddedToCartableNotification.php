<?php

namespace App\Listeners;

use App\Events\AddedToCartable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendAddedToCartableNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AddedToCartable $event): void
    {
        //
    }
}
