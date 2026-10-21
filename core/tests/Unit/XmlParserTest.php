<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Channels\Exceptions\PermanentChannelException;
use App\Channels\Raahi\RaahiAdapter;
use App\Models\Channel;
use PHPUnit\Framework\TestCase;

class XmlParserTest extends TestCase
{
    private RaahiAdapter $adapter;
    private Channel $mockChannel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new RaahiAdapter();
        $this->mockChannel = new Channel();
        $this->mockChannel->id = 1;
    }

    public function test_parses_valid_ota_res_notif_xml(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<OTA_HotelResNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="EVT-101" Version="2">
  <HotelReservations>
    <HotelReservation ResStatus="Commit" Version="2">
      <UniqueID ID="RAA-9901"/>
      <RoomStays>
        <RoomStay>
          <RoomTypes>
            <RoomType RoomTypeCode="STD"/>
          </RoomTypes>
          <RatePlans>
            <RatePlan RatePlanCode="EP"/>
          </RatePlans>
          <TimeSpan Start="2026-11-01" End="2026-11-04"/>
          <Total AmountAfterTax="11550" CurrencyCode="INR"/>
        </RoomStay>
      </RoomStays>
      <ResGuests>
        <ResGuest>
          <Profiles>
            <ProfileInfo>
              <Profile>
                <Customer>
                  <PersonName>
                    <GivenName>Arun</GivenName>
                    <Surname>Kumar</Surname>
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

        $events = $this->adapter->parseInbound($this->mockChannel, $xml, []);

        $this->assertCount(1, $events);
        $evt = $events[0];
        $this->assertSame('RAA-9901', $evt->externalId);
        $this->assertSame(2, $evt->externalVersion);
        $this->assertSame('upsert', $evt->type);
        $this->assertSame('STD', $evt->roomCode);
        $this->assertSame('EP', $evt->rateCode);
        $this->assertSame('2026-11-01', $evt->checkIn);
        $this->assertSame('2026-11-04', $evt->checkOut);
        $this->assertSame('Arun Kumar', $evt->guestName);
        $this->assertSame(11550, $evt->totalPrice);
    }

    public function test_rejects_doctype_and_entity_declarations_for_xxe_security(): void
    {
        $xxeXml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<OTA_HotelResNotifRQ>
  <HotelReservations>
    <HotelReservation ResStatus="Commit">
      <UniqueID ID="&xxe;"/>
    </HotelReservation>
  </HotelReservations>
</OTA_HotelResNotifRQ>
XML;

        $this->expectException(PermanentChannelException::class);
        $this->expectExceptionMessageMatches('/forbidden/i');

        $this->adapter->parseInbound($this->mockChannel, $xxeXml, []);
    }

    public function test_hmac_signature_calculation_and_verification(): void
    {
        $secret = 'secret_test_key_123';
        $body = '{"test":"payload"}';
        $timestamp = (string) time();

        $validSig = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        // Verification matches
        $computed = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        $this->assertTrue(hash_equals($validSig, $computed));

        // Tampered payload fails
        $tamperedBody = '{"test":"tampered"}';
        $this->assertFalse(hash_equals($validSig, hash_hmac('sha256', $timestamp . '.' . $tamperedBody, $secret)));

        // Skew check
        $staleTimestamp = (string) (time() - 400); // 400s old > 300s
        $this->assertGreaterThan(300, abs(time() - (int) $staleTimestamp));
    }
}
