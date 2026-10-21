<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\Dto\AvailabilityRange;
use App\Channels\Dto\RateRange;
use App\Channels\Dto\SyncBatch;
use App\Models\Channel;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SyncPlanner
{
    /**
     * Retrieves the next dirty batch for a channel (availability first, then rates).
     */
    public function nextBatch(Channel $channel): ?SyncBatch
    {
        $today = now()->timezone('Asia/Kolkata')->toDateString();

        // 1. Try Availability dirty rows
        $availRows = DB::select(
            "SELECT s.id AS state_id, s.subject_id AS room_type_id, s.stay_date,
                    GREATEST(ri.available, 0) AS avail, ri.stop_sell, ri.version
             FROM channel_sync_state s
             JOIN room_inventory ri ON ri.room_type_id = s.subject_id AND ri.stay_date = s.stay_date
             WHERE s.channel_id = ? AND s.kind = 'availability'
               AND s.blocked_at IS NULL AND ri.version > s.pushed_version
               AND s.stay_date >= ?
             ORDER BY s.subject_id, s.stay_date
             LIMIT 2000",
            [$channel->id, $today]
        );

        if (!empty($availRows)) {
            return $this->buildAvailabilityBatch($channel, $availRows);
        }

        // 2. Try Rate dirty rows
        $rateRows = DB::select(
            "SELECT s.id AS state_id, s.subject_id AS rate_plan_id, s.stay_date,
                    rp.price, rp.version
             FROM channel_sync_state s
             JOIN rate_prices rp ON rp.rate_plan_id = s.subject_id AND rp.stay_date = s.stay_date
             WHERE s.channel_id = ? AND s.kind = 'rate'
               AND s.blocked_at IS NULL AND rp.version > s.pushed_version
               AND s.stay_date >= ?
             ORDER BY s.subject_id, s.stay_date
             LIMIT 2000",
            [$channel->id, $today]
        );

        if (!empty($rateRows)) {
            return $this->buildRateBatch($channel, $rateRows);
        }

        return null;
    }

    /**
     * Coalesce consecutive dates with equal (avail, stop_sell) per room type into ranges.
     *
     * @param list<object> $rows
     */
    private function buildAvailabilityBatch(Channel $channel, array $rows): SyncBatch
    {
        // Load room code mappings for this channel
        $mappings = ChannelRoomMapping::where('channel_id', $channel->id)
            ->pluck('external_room_code', 'room_type_id')
            ->all();

        $ranges = [];
        $stateVersions = [];

        // Group rows by room_type_id
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->room_type_id][] = $row;
            $stateVersions[(int) $row->state_id] = (int) $row->version;
        }

        foreach ($grouped as $roomTypeId => $roomRows) {
            $extCode = $mappings[$roomTypeId] ?? (string) $roomTypeId;

            $currentRange = null;

            foreach ($roomRows as $row) {
                $dateStr = (string) $row->stay_date;
                $avail = (int) $row->avail;
                $stopSell = (bool) $row->stop_sell;
                $stateId = (int) $row->state_id;
                $version = (int) $row->version;

                if ($currentRange === null) {
                    $currentRange = [
                        'roomTypeId' => (int) $roomTypeId,
                        'externalRoomCode' => $extCode,
                        'startDate' => $dateStr,
                        'endDate' => $dateStr,
                        'avail' => $avail,
                        'stopSell' => $stopSell,
                        'stateIds' => [$stateId],
                        'maxVersion' => $version,
                    ];
                    continue;
                }

                $prevEnd = Carbon::parse($currentRange['endDate']);
                $currDate = Carbon::parse($dateStr);
                $isConsecutive = $prevEnd->copy()->addDay()->isSameDay($currDate);
                $isSameValues = ($currentRange['avail'] === $avail && $currentRange['stopSell'] === $stopSell);

                if ($isConsecutive && $isSameValues) {
                    $currentRange['endDate'] = $dateStr;
                    $currentRange['stateIds'][] = $stateId;
                    $currentRange['maxVersion'] = max($currentRange['maxVersion'], $version);
                } else {
                    $ranges[] = new AvailabilityRange(
                        roomTypeId: $currentRange['roomTypeId'],
                        externalRoomCode: $currentRange['externalRoomCode'],
                        startDate: $currentRange['startDate'],
                        endDate: $currentRange['endDate'],
                        available: $currentRange['avail'],
                        stopSell: $currentRange['stopSell'],
                        stateIds: $currentRange['stateIds'],
                        versionSeen: $currentRange['maxVersion'],
                    );

                    $currentRange = [
                        'roomTypeId' => (int) $roomTypeId,
                        'externalRoomCode' => $extCode,
                        'startDate' => $dateStr,
                        'endDate' => $dateStr,
                        'avail' => $avail,
                        'stopSell' => $stopSell,
                        'stateIds' => [$stateId],
                        'maxVersion' => $version,
                    ];
                }
            }

            if ($currentRange !== null) {
                $ranges[] = new AvailabilityRange(
                    roomTypeId: $currentRange['roomTypeId'],
                    externalRoomCode: $currentRange['externalRoomCode'],
                    startDate: $currentRange['startDate'],
                    endDate: $currentRange['endDate'],
                    available: $currentRange['avail'],
                    stopSell: $currentRange['stopSell'],
                    stateIds: $currentRange['stateIds'],
                    versionSeen: $currentRange['maxVersion'],
                );
            }
        }

        return new SyncBatch(
            channelId: $channel->id,
            kind: 'availability',
            ranges: $ranges,
            stateVersions: $stateVersions,
        );
    }

    /**
     * Coalesce consecutive dates with equal price per rate plan into ranges.
     *
     * @param list<object> $rows
     */
    private function buildRateBatch(Channel $channel, array $rows): SyncBatch
    {
        $mappings = ChannelRateMapping::where('channel_id', $channel->id)
            ->pluck('external_rate_code', 'rate_plan_id')
            ->all();

        $ranges = [];
        $stateVersions = [];

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->rate_plan_id][] = $row;
            $stateVersions[(int) $row->state_id] = (int) $row->version;
        }

        foreach ($grouped as $ratePlanId => $planRows) {
            $extCode = $mappings[$ratePlanId] ?? (string) $ratePlanId;

            $currentRange = null;

            foreach ($planRows as $row) {
                $dateStr = (string) $row->stay_date;
                $price = (int) $row->price;
                $markedUpPrice = RateService::applyMarkup($price, $channel->markup_bps);
                $stateId = (int) $row->state_id;
                $version = (int) $row->version;

                if ($currentRange === null) {
                    $currentRange = [
                        'ratePlanId' => (int) $ratePlanId,
                        'externalRateCode' => $extCode,
                        'startDate' => $dateStr,
                        'endDate' => $dateStr,
                        'price' => $price,
                        'markedUpPrice' => $markedUpPrice,
                        'stateIds' => [$stateId],
                        'maxVersion' => $version,
                    ];
                    continue;
                }

                $prevEnd = Carbon::parse($currentRange['endDate']);
                $currDate = Carbon::parse($dateStr);
                $isConsecutive = $prevEnd->copy()->addDay()->isSameDay($currDate);
                $isSamePrice = ($currentRange['price'] === $price);

                if ($isConsecutive && $isSamePrice) {
                    $currentRange['endDate'] = $dateStr;
                    $currentRange['stateIds'][] = $stateId;
                    $currentRange['maxVersion'] = max($currentRange['maxVersion'], $version);
                } else {
                    $ranges[] = new RateRange(
                        ratePlanId: $currentRange['ratePlanId'],
                        externalRateCode: $currentRange['externalRateCode'],
                        startDate: $currentRange['startDate'],
                        endDate: $currentRange['endDate'],
                        price: $currentRange['price'],
                        markedUpPrice: $currentRange['markedUpPrice'],
                        stateIds: $currentRange['stateIds'],
                        versionSeen: $currentRange['maxVersion'],
                    );

                    $currentRange = [
                        'ratePlanId' => (int) $ratePlanId,
                        'externalRateCode' => $extCode,
                        'startDate' => $dateStr,
                        'endDate' => $dateStr,
                        'price' => $price,
                        'markedUpPrice' => $markedUpPrice,
                        'stateIds' => [$stateId],
                        'maxVersion' => $version,
                    ];
                }
            }

            if ($currentRange !== null) {
                $ranges[] = new RateRange(
                    ratePlanId: $currentRange['ratePlanId'],
                    externalRateCode: $currentRange['externalRateCode'],
                    startDate: $currentRange['startDate'],
                    endDate: $currentRange['endDate'],
                    price: $currentRange['price'],
                    markedUpPrice: $currentRange['markedUpPrice'],
                    stateIds: $currentRange['stateIds'],
                    versionSeen: $currentRange['maxVersion'],
                );
            }
        }

        return new SyncBatch(
            channelId: $channel->id,
            kind: 'rate',
            ranges: $ranges,
            stateVersions: $stateVersions,
        );
    }

    /**
     * Updates channel_sync_state.pushed_version with seen versions using CASE statement.
     * Never writes current version, only seen version.
     */
    public function markPushed(Channel $channel, SyncBatch $batch): void
    {
        if (empty($batch->stateVersions)) {
            return;
        }

        $cases = [];
        $ids = [];
        $bindings = [];

        foreach ($batch->stateVersions as $stateId => $versionSeen) {
            $ids[] = $stateId;
            $cases[] = "WHEN {$stateId} THEN {$versionSeen}";
        }

        $caseSql = implode(' ', $cases);
        $inList = implode(',', $ids);

        // Section 7.3:
        // UPDATE channel_sync_state
        // SET pushed_version = CASE id ... END, attempts = 0, last_error = NULL
        // WHERE id IN (...) AND pushed_version < CASE id ... END;
        $sql = "UPDATE channel_sync_state
                SET pushed_version = CASE id {$caseSql} END,
                    attempts = 0,
                    last_error = NULL,
                    updated_at = NOW()
                WHERE id IN ({$inList})
                  AND pushed_version < CASE id {$caseSql} END";

        DB::update($sql);
    }

    public function quarantine(Channel $channel, SyncBatch $batch, string $error): void
    {
        if (empty($batch->stateVersions)) {
            return;
        }

        $ids = array_keys($batch->stateVersions);
        DB::table('channel_sync_state')
            ->whereIn('id', $ids)
            ->update([
                'blocked_at' => now(),
                'last_error' => substr($error, 0, 1000),
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);
    }
}
