<?php

declare(strict_types=1);

namespace App\Channels\Mela;

use App\Channels\ChannelAdapter;
use App\Channels\Dto\AvailabilityRange;
use App\Channels\Dto\Capabilities;
use App\Channels\Dto\RateRange;
use App\Channels\Dto\ReservationBatch;
use App\Channels\Dto\ReservationEvent;
use App\Channels\Exceptions\PermanentChannelException;
use App\Enums\SyncDirection;
use App\Enums\SyncOutcome;
use App\Models\Channel;
use App\Models\SyncLog;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Str;

class MelaAdapter implements ChannelAdapter
{
    private RemoteFileStore $fileStore;

    public function __construct(?RemoteFileStore $fileStore = null)
    {
        $path = config('services.mela.storage_path', storage_path('sftp'));
        $this->fileStore = $fileStore ?? new RemoteFileStore($path);
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(
            supportsPushAvailability: true,
            supportsPushRates: true,
            supportsPullReservations: true,
            supportsWebhooks: false,
            supportsRealtime: false,
        );
    }

    public function pushAvailability(Channel $c, array $ranges, string $correlationId): void
    {
        $this->writeInventoryCsv($c, $ranges, [], $correlationId, 'pushAvailability');
    }

    public function pushRates(Channel $c, array $ranges, string $correlationId): void
    {
        $this->writeInventoryCsv($c, [], $ranges, $correlationId, 'pushRates');
    }

    public function pullReservations(Channel $c, ?string $cursor): ReservationBatch
    {
        $files = $this->fileStore->listFiles('sftp_in/mela');
        sort($files);

        $allEvents = [];
        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            if (!str_starts_with($fileName, 'reservations_') || !str_ends_with($fileName, '.csv')) {
                continue;
            }

            $content = $this->fileStore->get($filePath);
            $lines = explode("\n", trim($content));
            if (empty($lines)) {
                continue;
            }

            $header = str_getcsv(array_shift($lines));
            $headerMap = array_flip($header);

            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $row = str_getcsv($line);

                $allEvents[] = new ReservationEvent(
                    channelId: $c->id,
                    externalId: $row[$headerMap['external_id'] ?? 0] ?? (string) Str::uuid(),
                    externalVersion: (int) ($row[$headerMap['version'] ?? 1] ?? 1),
                    type: $row[$headerMap['type'] ?? 2] ?? 'upsert',
                    roomCode: $row[$headerMap['room_code'] ?? 3] ?? '',
                    rateCode: $row[$headerMap['rate_code'] ?? 4] ?? '',
                    checkIn: $row[$headerMap['check_in'] ?? 5] ?? '',
                    checkOut: $row[$headerMap['check_out'] ?? 6] ?? '',
                    guestName: $row[$headerMap['guest_name'] ?? 7] ?? 'Guest',
                    adults: (int) ($row[$headerMap['adults'] ?? 8] ?? 1),
                    totalPrice: (int) ($row[$headerMap['total_price'] ?? 9] ?? 0),
                    currency: $row[$headerMap['currency'] ?? 10] ?? 'INR',
                    externalEventId: $fileName . '_' . ($row[$headerMap['external_id'] ?? 0] ?? Str::uuid()),
                );
            }

            // Move file to processed/ only after reading
            $this->fileStore->move($filePath, 'sftp_in/mela/processed', $fileName);
        }

        return new ReservationBatch(events: $allEvents, nextCursor: null);
    }

    public function parseInbound(Channel $c, string $rawBody, array $headers): array
    {
        $lines = explode("\n", trim($rawBody));
        if (empty($lines)) {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        $headerMap = array_flip($header);
        $events = [];

        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $row = str_getcsv($line);

            $events[] = new ReservationEvent(
                channelId: $c->id,
                externalId: $row[$headerMap['external_id'] ?? 0] ?? (string) Str::uuid(),
                externalVersion: (int) ($row[$headerMap['version'] ?? 1] ?? 1),
                type: $row[$headerMap['type'] ?? 2] ?? 'upsert',
                roomCode: $row[$headerMap['room_code'] ?? 3] ?? '',
                rateCode: $row[$headerMap['rate_code'] ?? 4] ?? '',
                checkIn: $row[$headerMap['check_in'] ?? 5] ?? '',
                checkOut: $row[$headerMap['check_out'] ?? 6] ?? '',
                guestName: $row[$headerMap['guest_name'] ?? 7] ?? 'Guest',
                adults: (int) ($row[$headerMap['adults'] ?? 8] ?? 1),
                totalPrice: (int) ($row[$headerMap['total_price'] ?? 9] ?? 0),
                currency: $row[$headerMap['currency'] ?? 10] ?? 'INR',
                externalEventId: (string) ($row[$headerMap['event_id'] ?? 0] ?? Str::uuid()),
            );
        }

        return $events;
    }

    /**
     * @param list<AvailabilityRange> $availRanges
     * @param list<RateRange> $rateRanges
     */
    private function writeInventoryCsv(
        Channel $channel,
        array $availRanges,
        array $rateRanges,
        string $correlationId,
        string $operation
    ): void {
        $startTime = hrtime(true);
        $fileName = 'inventory_' . date('YmdHis') . '_' . substr($correlationId, 0, 8) . '.csv';

        $rows = [];
        $rows[] = ['hotel_code', 'room_code', 'rate_code', 'date', 'available', 'price', 'stop_sell'];

        foreach ($availRanges as $range) {
            $period = CarbonPeriod::create($range->startDate, $range->endDate);
            foreach ($period as $dt) {
                $rows[] = [
                    'LOTUS01',
                    $range->externalRoomCode,
                    'EP',
                    $dt->toDateString(),
                    $range->stopSell ? 0 : $range->available,
                    0,
                    $range->stopSell ? 1 : 0,
                ];
            }
        }

        foreach ($rateRanges as $range) {
            $period = CarbonPeriod::create($range->startDate, $range->endDate);
            foreach ($period as $dt) {
                $rows[] = [
                    'LOTUS01',
                    'STD',
                    $range->externalRateCode,
                    $dt->toDateString(),
                    0,
                    $range->markedUpPrice,
                    0,
                ];
            }
        }

        $fp = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $csvContent = stream_get_contents($fp);
        fclose($fp);

        $this->fileStore->put('sftp_out/mela', $fileName, $csvContent);

        $durationMs = (int) round((hrtime(true) - $startTime) / 1e6);

        SyncLog::create([
            'correlation_id' => $correlationId,
            'channel_id' => $channel->id,
            'direction' => SyncDirection::Out,
            'operation' => $operation,
            'request_body' => substr($csvContent, 0, 65535),
            'response_status' => 200,
            'response_body' => 'File written: ' . $fileName,
            'duration_ms' => $durationMs,
            'attempt' => 1,
            'outcome' => SyncOutcome::Ok,
            'error' => null,
            'created_at' => now(),
        ]);
    }
}
