<?php

declare(strict_types=1);

namespace App\Channels\Raahi;

use App\Channels\ChannelAdapter;
use App\Channels\Dto\AvailabilityRange;
use App\Channels\Dto\Capabilities;
use App\Channels\Dto\RateRange;
use App\Channels\Dto\ReservationBatch;
use App\Channels\Dto\ReservationEvent;
use App\Channels\Exceptions\PermanentChannelException;
use App\Channels\Exceptions\TransientChannelException;
use App\Enums\SyncDirection;
use App\Enums\SyncOutcome;
use App\Models\Channel;
use App\Models\SyncLog;
use DOMDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class RaahiAdapter implements ChannelAdapter
{
    public function capabilities(): Capabilities
    {
        return new Capabilities(
            supportsPushAvailability: true,
            supportsPushRates: true,
            supportsPullReservations: false,
            supportsWebhooks: true,
            supportsRealtime: false,
        );
    }

    public function pushAvailability(Channel $c, array $ranges, string $correlationId): void
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElement('OTA_HotelAvailNotifRQ');
        $root->setAttribute('xmlns', 'http://www.opentravel.org/OTA/2003/05');
        $xml->appendChild($root);

        $messages = $xml->createElement('AvailStatusMessages');
        $messages->setAttribute('HotelCode', 'LOTUS01');
        $root->appendChild($messages);

        foreach ($ranges as $range) {
            $msg = $xml->createElement('AvailStatusMessage');
            $msg->setAttribute('BookingLimit', (string) $range->available);

            $statusCtrl = $xml->createElement('StatusApplicationControl');
            $statusCtrl->setAttribute('Start', $range->startDate);
            $statusCtrl->setAttribute('End', $range->endDate);
            $statusCtrl->setAttribute('InvTypeCode', $range->externalRoomCode);
            if ($range->stopSell) {
                $statusCtrl->setAttribute('RestrictionStatus', 'Close');
            }

            $msg->appendChild($statusCtrl);
            $messages->appendChild($msg);
        }

        $xmlPayload = $xml->saveXML();
        $this->sendXml($c, '/raahi/availability', $xmlPayload, $correlationId, 'pushAvailability');
    }

    public function pushRates(Channel $c, array $ranges, string $correlationId): void
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElement('OTA_HotelRateAmountNotifRQ');
        $root->setAttribute('xmlns', 'http://www.opentravel.org/OTA/2003/05');
        $xml->appendChild($root);

        $messages = $xml->createElement('RateAmountMessages');
        $messages->setAttribute('HotelCode', 'LOTUS01');
        $root->appendChild($messages);

        foreach ($ranges as $range) {
            $msg = $xml->createElement('RateAmountMessage');

            $statusCtrl = $xml->createElement('StatusApplicationControl');
            $statusCtrl->setAttribute('Start', $range->startDate);
            $statusCtrl->setAttribute('End', $range->endDate);
            $statusCtrl->setAttribute('RatePlanCode', $range->externalRateCode);
            $msg->appendChild($statusCtrl);

            $rates = $xml->createElement('Rates');
            $rate = $xml->createElement('Rate');
            $baseAmts = $xml->createElement('BaseByGuestAmts');
            $baseAmt = $xml->createElement('BaseByGuestAmt');
            $baseAmt->setAttribute('AmountAfterTax', (string) $range->markedUpPrice);
            $baseAmt->setAttribute('CurrencyCode', 'INR');

            $baseAmts->appendChild($baseAmt);
            $rate->appendChild($baseAmts);
            $rates->appendChild($rate);
            $msg->appendChild($rates);

            $messages->appendChild($msg);
        }

        $xmlPayload = $xml->saveXML();
        $this->sendXml($c, '/raahi/rates', $xmlPayload, $correlationId, 'pushRates');
    }

    public function pullReservations(Channel $c, ?string $cursor): ReservationBatch
    {
        // Raahi is push-only via webhooks
        return new ReservationBatch(events: [], nextCursor: null);
    }

    public function parseInbound(Channel $c, string $rawBody, array $headers): array
    {
        // Check for DOCTYPE or entity expansion attacks (XXE prevention)
        if (stripos($rawBody, '<!DOCTYPE') !== false || stripos($rawBody, '<!ENTITY') !== false) {
            throw new PermanentChannelException('Malformed XML: DOCTYPE and ENTITY declarations are forbidden.');
        }

        $doc = new DOMDocument();
        $previousEntityLoader = libxml_disable_entity_loader(true);
        $loaded = @$doc->loadXML($rawBody, LIBXML_NONET);
        libxml_disable_entity_loader($previousEntityLoader);

        if (!$loaded) {
            throw new PermanentChannelException('Malformed XML received from Raahi.');
        }

        $root = $doc->documentElement;
        if ($root === null || $root->tagName !== 'OTA_HotelResNotifRQ') {
            throw new PermanentChannelException('Invalid root element, expected OTA_HotelResNotifRQ.');
        }

        $events = [];
        $hotelReservations = $doc->getElementsByTagName('HotelReservation');

        foreach ($hotelReservations as $resNode) {
            $resStatus = $resNode->getAttribute('ResStatus');
            $externalVersion = (int) ($resNode->getAttribute('Version') ?: '1');

            $uniqueIdNode = $resNode->getElementsByTagName('UniqueID')->item(0);
            $externalId = $uniqueIdNode ? $uniqueIdNode->getAttribute('ID') : '';
            if (empty($externalId)) {
                $externalId = (string) Str::uuid();
            }

            $type = strtolower($resStatus) === 'cancel' ? 'cancel' : 'upsert';

            $roomStay = $resNode->getElementsByTagName('RoomStay')->item(0);
            $roomCode = '';
            $rateCode = '';
            $startDate = '';
            $endDate = '';
            $totalPrice = 0;
            $currency = 'INR';

            if ($roomStay) {
                $roomTypeNode = $roomStay->getElementsByTagName('RoomType')->item(0);
                if ($roomTypeNode) {
                    $roomCode = $roomTypeNode->getAttribute('RoomTypeCode');
                }

                $ratePlanNode = $roomStay->getElementsByTagName('RatePlan')->item(0);
                if ($ratePlanNode) {
                    $rateCode = $ratePlanNode->getAttribute('RatePlanCode');
                }

                $timeSpan = $roomStay->getElementsByTagName('TimeSpan')->item(0);
                if ($timeSpan) {
                    $startDate = $timeSpan->getAttribute('Start');
                    $endDate = $timeSpan->getAttribute('End');
                }

                $total = $roomStay->getElementsByTagName('Total')->item(0);
                if ($total) {
                    $totalPrice = (int) round((float) $total->getAttribute('AmountAfterTax'));
                    $currency = $total->getAttribute('CurrencyCode') ?: 'INR';
                }
            }

            $guestName = 'Guest';
            $adults = 1;
            $customerNode = $resNode->getElementsByTagName('Customer')->item(0);
            if ($customerNode) {
                $personName = $customerNode->getElementsByTagName('PersonName')->item(0);
                if ($personName) {
                    $given = $personName->getElementsByTagName('GivenName')->item(0)?->nodeValue ?? '';
                    $sur = $personName->getElementsByTagName('Surname')->item(0)?->nodeValue ?? '';
                    $guestName = trim($given . ' ' . $sur) ?: 'Guest';
                }
            }

            $guestCounts = $resNode->getElementsByTagName('GuestCount');
            foreach ($guestCounts as $gc) {
                if ($gc->getAttribute('AgeQualifyingCode') === '10') { // 10 = Adult
                    $adults = (int) $gc->getAttribute('Count');
                }
            }

            $events[] = new ReservationEvent(
                channelId: $c->id,
                externalId: $externalId,
                externalVersion: $externalVersion,
                type: $type,
                roomCode: $roomCode,
                rateCode: $rateCode,
                checkIn: $startDate,
                checkOut: $endDate,
                guestName: $guestName,
                adults: max(1, $adults),
                totalPrice: $totalPrice,
                currency: $currency,
                externalEventId: (string) ($resNode->getAttribute('EchoToken') ?: $externalId . '_v' . $externalVersion),
            );
        }

        return $events;
    }

    private function sendXml(
        Channel $channel,
        string $endpoint,
        string $xmlPayload,
        string $correlationId,
        string $operation
    ): void {
        $baseUrl = rtrim((string) ($channel->base_url ?: config('services.sim.base_url')), '/');
        $url = $baseUrl . $endpoint;
        $creds = $channel->credentials ?? [];
        $apiKey = $creds['api_key'] ?? 'raahi_default_key';

        $startTime = hrtime(true);
        $outcome = SyncOutcome::Ok;
        $responseStatus = null;
        $responseBody = null;
        $errorMsg = null;

        try {
            $response = Http::timeout(10)
                ->connectTimeout(3)
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                    'X-Correlation-ID' => $correlationId,
                    'Content-Type' => 'application/xml',
                ])
                ->withBody($xmlPayload, 'application/xml')
                ->post($url);

            $responseStatus = $response->status();
            $responseBody = $response->body();

            if ($response->status() === 429) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 10);
                $outcome = SyncOutcome::TransientFail;
                $errorMsg = "HTTP 429 Rate limited. Retry-After: {$retryAfter}s";
                throw new TransientChannelException($errorMsg, 429, $retryAfter);
            }

            if ($response->serverError()) {
                $outcome = SyncOutcome::TransientFail;
                $errorMsg = "HTTP {$responseStatus} Server error: {$responseBody}";
                throw new TransientChannelException($errorMsg, $responseStatus);
            }

            if ($response->clientError()) {
                $outcome = SyncOutcome::PermanentFail;
                $errorMsg = "HTTP {$responseStatus} Client error: {$responseBody}";
                throw new PermanentChannelException($errorMsg, $responseStatus);
            }

            // Check if response contains XML <Errors>
            if (stripos($responseBody, '<Errors>') !== false) {
                $outcome = SyncOutcome::PermanentFail;
                $errorMsg = "Raahi returned XML Errors: {$responseBody}";
                throw new PermanentChannelException($errorMsg);
            }
        } catch (ConnectionException $e) {
            $outcome = SyncOutcome::TransientFail;
            $errorMsg = 'Connection error / timeout: ' . $e->getMessage();
            throw new TransientChannelException($errorMsg, 0, null, $e);
        } catch (TransientChannelException|PermanentChannelException $e) {
            throw $e;
        } catch (Throwable $e) {
            $outcome = SyncOutcome::TransientFail;
            $errorMsg = 'Unexpected error: ' . $e->getMessage();
            throw new TransientChannelException($errorMsg, 0, null, $e);
        } finally {
            $durationMs = (int) round((hrtime(true) - $startTime) / 1e6);

            SyncLog::create([
                'correlation_id' => $correlationId,
                'channel_id' => $channel->id,
                'direction' => SyncDirection::Out,
                'operation' => $operation,
                'request_body' => substr($xmlPayload, 0, 65535),
                'response_status' => $responseStatus,
                'response_body' => $responseBody ? substr($responseBody, 0, 65535) : null,
                'duration_ms' => $durationMs,
                'attempt' => 1,
                'outcome' => $outcome,
                'error' => $errorMsg,
                'created_at' => now(),
            ]);
        }
    }
}
