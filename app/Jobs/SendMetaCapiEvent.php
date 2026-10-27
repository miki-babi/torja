<?php

namespace App\Jobs;

use App\Models\TrackedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Forwards a tracked event to the Meta Conversions API (server-side), using
 * the integration's event_map and the event's event_uid as the dedup key
 * shared with the browser pixel. Not wired up yet — enable via the
 * `tracking.capi_enabled` flag when the Meta app + token are ready.
 */
class SendMetaCapiEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(public TrackedEvent $event) {}

    /**
     * Send the event to Meta's Conversions API for every enabled meta
     * integration that maps this event name.
     */
    public function handle(): void
    {
        // TODO: when tracking.capi_enabled — for each enabled meta
        // TrackingIntegration where event_map[name] exists and
        // config.access_token is set, POST {event_name, event_id:
        // event_uid, event_time, user_data: hashed em/ph/ip/ua,
        // custom_data: properties} to graph.facebook.com/{pixel}/events.
        // Keep raw health values (weight, body metrics) out of payloads.
    }
}
