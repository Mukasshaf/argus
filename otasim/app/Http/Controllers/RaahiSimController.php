<?php

declare(strict_types=1);

namespace Sim\Http\Controllers;

use Carbon\CarbonPeriod;
use DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RaahiSimController
{
    private function checkFault(): ?Response
    {
        $config = DB::table('sim_config')->where('channel_code', 'raahi')->first();
        if (!$config) {
            return null;
        }

        if ($config->latency_ms > 0) {
            usleep($config->latency_ms * 1000);
        }

        if ($config->mode === 'http500') {
            return response('<Errors><Error Code="500" ShortText="Internal Simulator Error"/></Errors>', 500, ['Content-Type' => 'application/xml']);
        }
        if ($config->mode === 'http429') {
            return response('<Errors><Error Code="429" ShortText="Too Many Requests"/></Errors>', 429, [
                'Content-Type' => 'application/xml',
                'Retry-After' => '10',
            ]);
        }
        if ($config->mode === 'reject400') {
            return response('<Errors><Error Code="400" ShortText="Validation Exception"/></Errors>', 400, ['Content-Type' => 'application/xml']);
        }

        return null;
    }

    public function handleAvailability(Request $request): Response
    {
        if ($fault = $this->checkFault()) {
            return $fault;
        }

        $xmlStr = (string) $request->getContent();
        $doc = new DOMDocument();
        if (@$doc->loadXML($xmlStr)) {
            $messages = $doc->getElementsByTagName('AvailStatusMessage');
            foreach ($messages as $msg) {
                $limit = (int) $msg->getAttribute('BookingLimit');
                $ctrl = $msg->getElementsByTagName('StatusApplicationControl')->item(0);
                if ($ctrl) {
                    $start = $ctrl->getAttribute('Start');
                    $end = $ctrl->getAttribute('End');
                    $roomCode = $ctrl->getAttribute('InvTypeCode');
                    $period = CarbonPeriod::create($start, $end);
                    foreach ($period as $d) {
                        $key = "{$roomCode}:{$d->toDateString()}";
                        DB::table('sim_state')->updateOrInsert(
                            ['channel_code' => 'raahi', 'kind' => 'availability', 'key' => $key],
                            ['value' => $limit, 'updated_at' => now()]
                        );
                    }
                }
            }
        }

        $resXml = '<OTA_HotelAvailNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"><Success/></OTA_HotelAvailNotifRS>';
        return response($resXml, 200, ['Content-Type' => 'application/xml']);
    }

    public function handleRates(Request $request): Response
    {
        if ($fault = $this->checkFault()) {
            return $fault;
        }

        $xmlStr = (string) $request->getContent();
        $doc = new DOMDocument();
        if (@$doc->loadXML($xmlStr)) {
            $messages = $doc->getElementsByTagName('RateAmountMessage');
            foreach ($messages as $msg) {
                $ctrl = $msg->getElementsByTagName('StatusApplicationControl')->item(0);
                $amtNode = $msg->getElementsByTagName('BaseByGuestAmt')->item(0);
                if ($ctrl && $amtNode) {
                    $start = $ctrl->getAttribute('Start');
                    $end = $ctrl->getAttribute('End');
                    $rateCode = $ctrl->getAttribute('RatePlanCode');
                    $amt = (int) round((float) $amtNode->getAttribute('AmountAfterTax'));
                    $period = CarbonPeriod::create($start, $end);
                    foreach ($period as $d) {
                        $key = "{$rateCode}:{$d->toDateString()}";
                        DB::table('sim_state')->updateOrInsert(
                            ['channel_code' => 'raahi', 'kind' => 'rate', 'key' => $key],
                            ['value' => $amt, 'updated_at' => now()]
                        );
                    }
                }
            }
        }

        $resXml = '<OTA_HotelRateAmountNotifRS xmlns="http://www.opentravel.org/OTA/2003/05"><Success/></OTA_HotelRateAmountNotifRS>';
        return response($resXml, 200, ['Content-Type' => 'application/xml']);
    }

    public function sendWebhook(Request $request): \Illuminate\Http\JsonResponse
    {
        $coreUrl = rtrim(env('CORE_BASE_URL', 'http://localhost:8000'), '/');
        $endpoint = $coreUrl . '/api/inbound/raahi/webhook';
        $secret = 'raahi_webhook_secret_hmac_sha256';

        $externalId = $request->input('external_id', 'RAA-' . random_int(100000, 999999));
        $version = (int) $request->input('version', 1);
        $status = $request->input('status', 'Commit'); // Commit, Modify, Cancel
        $roomCode = $request->input('room_code', 'STD');
        $rateCode = $request->input('rate_code', 'EP');
        $checkIn = $request->input('check_in', now()->toDateString());
        $checkOut = $request->input('check_out', now()->addDays(2)->toDateString());
        $guestName = $request->input('guest_name', 'Rahul Sharma');
        $totalPrice = (int) $request->input('total_price', 7700);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<OTA_HotelResNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="{$externalId}_v{$version}" TimeStamp="2026-10-21T12:00:00Z" Version="{$version}">
  <HotelReservations>
    <HotelReservation ResStatus="{$status}" Version="{$version}">
      <UniqueID ID="{$externalId}" Type="14"/>
      <RoomStays>
        <RoomStay>
          <RoomTypes>
            <RoomType RoomTypeCode="{$roomCode}"/>
          </RoomTypes>
          <RatePlans>
            <RatePlan RatePlanCode="{$rateCode}"/>
          </RatePlans>
          <TimeSpan Start="{$checkIn}" End="{$checkOut}"/>
          <Total AmountAfterTax="{$totalPrice}" CurrencyCode="INR"/>
        </RoomStay>
      </RoomStays>
      <ResGuests>
        <ResGuest>
          <Profiles>
            <ProfileInfo>
              <Profile>
                <Customer>
                  <PersonName>
                    <GivenName>{$guestName}</GivenName>
                  </PersonName>
                </Customer>
              </Profile>
            </ProfileInfo>
          </Profiles>
        </ResGuest>
      </ResGuests>
    </HotelReservation>
  </HotelReservations>
</OTA_HotelResNotifRQ>
XML;

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $xml, $secret);

        $res = Http::withHeaders([
            'Content-Type' => 'application/xml',
            'X-Timestamp' => $timestamp,
            'X-Signature' => 'sha256=' . $signature,
            'X-Channel-Code' => 'raahi',
        ])->withBody($xml, 'application/xml')->post($endpoint);

        return response()->json([
            'status' => $res->status(),
            'body' => $res->json() ?? $res->body(),
        ]);
    }
}
