<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inbound;

use App\Enums\InboundEventStatus;
use App\Http\Controllers\Controller;
use App\Jobs\IngestInboundEventJob;
use App\Models\Channel;
use App\Models\InboundEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RaahiWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        /** @var Channel $channel */
        $channel = $request->attributes->get('channel')
            ?? Channel::where('code', 'raahi')->firstOrFail();

        $rawBody = (string) $request->getContent();
        if (trim($rawBody) === '') {
            return response()->json(['error' => 'Malformed or empty body'], 400);
        }

        // Extract external_event_id from XML if possible, else sha256 hash
        $eventId = null;
        if (preg_match('/EchoToken=["\']([^"\']+)["\']/', $rawBody, $m)) {
            $eventId = $m[1];
        } elseif (preg_match('/<UniqueID[^>]*ID=["\']([^"\']+)["\']/', $rawBody, $m)) {
            $version = 1;
            if (preg_match('/Version=["\']([^"\']+)["\']/', $rawBody, $vm)) {
                $version = $vm[1];
            }
            $eventId = $m[1] . '_v' . $version;
        }

        if (!$eventId) {
            $eventId = 'raahi_' . hash('sha256', $rawBody);
        }

        // Idempotent insertion
        $inbound = InboundEvent::firstOrCreate(
            [
                'channel_id' => $channel->id,
                'external_event_id' => $eventId,
            ],
            [
                'kind' => 'webhook_reservation',
                'payload' => $rawBody,
                'status' => InboundEventStatus::Received,
                'received_at' => now(),
            ]
        );

        if ($inbound->wasRecentlyCreated) {
            IngestInboundEventJob::dispatch($inbound->id);
        }

        return response()->json(['status' => 'accepted', 'event_id' => $inbound->id], 202);
    }
}
