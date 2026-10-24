<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\Dto\ReservationEvent;
use App\Channels\Exceptions\MissingInventoryException;
use App\Channels\Exceptions\UnmappedCodeException;
use App\Enums\IngestResult;
use App\Enums\ReservationStatus;
use App\Models\Alert;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationNight;
use App\Models\RoomInventory;
use Illuminate\Support\Facades\DB;

class ReservationIngestService
{
    // Test flags for negative controls (NC2, NC3)
    public static bool $skipClaimAndLock = false; // NC2: skip row claim/lock
    public static bool $useReadModifyWrite = false; // NC3: PHP read-modify-write losing updates

    public function apply(ReservationEvent $e): IngestResult
    {
        $touchedByRoomType = [];
        $hotelId = 1;

        // The room, rate and hotel lookups happen outside the transaction on
        // purpose. Under REPEATABLE READ a transaction takes its read view at
        // its first consistent read, and that view then stays fixed for the
        // whole transaction. Reading them inside would pin the view to the
        // moment the delivery arrived, so a concurrent delivery that had
        // already committed an extended stay would still be invisible when
        // the nights are diffed below, and the cancellation would release
        // only the nights it remembers. Resolving them first means the first
        // consistent read inside the transaction is the nights read, which
        // happens after the reservation row has been locked, so the view and
        // the locked row agree.
        $room = ChannelRoomMapping::resolve($e->channelId, $e->roomCode);
        $rate = ChannelRateMapping::resolve($e->channelId, $e->rateCode);

        if (!$room || !$rate) {
            Alert::unmappedCode($e->channelId, 'reservation', "Room: {$e->roomCode}, Rate: {$e->rateCode}");
            throw new UnmappedCodeException($e, "Unmapped room [{$e->roomCode}] or rate [{$e->rateCode}] code.");
        }

        $hotel = Hotel::first();
        if ($hotel) {
            $hotelId = $hotel->id;
        }

        $result = DB::transaction(function () use ($e, $room, $rate, $hotelId, &$touchedByRoomType) {
            // 1. Claim: Insert a version 0 placeholder if absent, then lock the row (unless NC2 active)
            if (!self::$skipClaimAndLock) {
                // ON DUPLICATE KEY UPDATE is used rather than INSERT IGNORE.
                // INSERT IGNORE takes a shared lock on a row that already exists,
                // and the FOR UPDATE below then has to upgrade that shared lock
                // to an exclusive one. Concurrent deliveries of the same booking
                // all hold the shared lock and all wait for the upgrade, which
                // deadlocks them against each other. ON DUPLICATE KEY UPDATE
                // takes the exclusive lock straight away, so the deliveries
                // queue on the row and the version check below sees them one
                // at a time. The update clause is a no-op, so an existing
                // reservation keeps its external_version.
                DB::table('reservations')->upsert(
                    [[
                        'hotel_id' => $hotelId,
                        'room_type_id' => $room->room_type_id,
                        'rate_plan_id' => $rate->rate_plan_id,
                        'channel_id' => $e->channelId,
                        'external_id' => $e->externalId,
                        'external_version' => 0,
                        'status' => ReservationStatus::Confirmed->value,
                        'check_in' => $e->checkIn ?: now()->toDateString(),
                        'check_out' => $e->checkOut ?: now()->addDay()->toDateString(),
                        'guest_name' => $e->guestName,
                        'adults' => $e->adults,
                        'total_price' => $e->totalPrice,
                        'currency' => $e->currency,
                        'received_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]],
                    ['channel_id', 'external_id'],
                    ['external_version' => DB::raw('external_version')],
                );

                $res = Reservation::where('channel_id', $e->channelId)
                    ->where('external_id', $e->externalId)
                    ->lockForUpdate()
                    ->first();
            } else {
                // NC2: skip claim and lock
                $res = Reservation::where('channel_id', $e->channelId)
                    ->where('external_id', $e->externalId)
                    ->first();
                if (!$res) {
                    $res = Reservation::create([
                        'hotel_id' => $hotelId,
                        'room_type_id' => $room->room_type_id,
                        'rate_plan_id' => $rate->rate_plan_id,
                        'channel_id' => $e->channelId,
                        'external_id' => $e->externalId,
                        'external_version' => 0,
                        'status' => ReservationStatus::Confirmed,
                        'check_in' => $e->checkIn ?: now()->toDateString(),
                        'check_out' => $e->checkOut ?: now()->addDay()->toDateString(),
                        'guest_name' => $e->guestName,
                        'adults' => $e->adults,
                        'total_price' => $e->totalPrice,
                        'currency' => $e->currency,
                        'received_at' => now(),
                    ]);
                }
            }

            // Inbound version check: monotonically increasing
            if ($res && $e->externalVersion <= $res->external_version) {
                return IngestResult::Duplicate;
            }

            // 2. Diff nights
            // This is the first consistent read of the transaction, so the read
            // view is taken here, after the reservation row was locked. The
            // nights therefore match the reservation row that was just read.
            $oldNights = $res ? $res->nights()->pluck('stay_date')->map(fn($d) => (string) $d)->all() : [];
            $oldRoomTypeId = $res ? $res->room_type_id : $room->room_type_id;
            $newNights = $e->type === 'cancel' ? [] : Nights::between($e->checkIn, $e->checkOut);

            // Handle room type changes or multi-night diff
            $released = [];
            $taken = [];

            if ($oldRoomTypeId === $room->room_type_id) {
                // Same room type: released nights are in old but not in new
                $released[$oldRoomTypeId] = array_values(array_diff($oldNights, $newNights));
                // Taken nights are in new but not in old
                $taken[$room->room_type_id] = array_values(array_diff($newNights, $oldNights));
            } else {
                // Room type changed: release all old on old room type, take all new on new room type
                $released[$oldRoomTypeId] = $oldNights;
                $taken[$room->room_type_id] = $newNights;
            }

            // 3. Collect and lock all touched inventory rows in strict ascending order (room_type_id, stay_date)
            $allTouched = [];
            foreach ($released as $rtId => $dates) {
                foreach ($dates as $d) {
                    $allTouched[] = ['room_type_id' => $rtId, 'stay_date' => $d];
                }
            }
            foreach ($taken as $rtId => $dates) {
                foreach ($dates as $d) {
                    $allTouched[] = ['room_type_id' => $rtId, 'stay_date' => $d];
                }
            }

            // Unique and sort ascending
            usort($allTouched, function ($a, $b) {
                if ($a['room_type_id'] !== $b['room_type_id']) {
                    return $a['room_type_id'] <=> $b['room_type_id'];
                }
                return strcmp($a['stay_date'], $b['stay_date']);
            });

            // Lock inventory rows in order
            foreach ($allTouched as $item) {
                $invRow = DB::table('room_inventory')
                    ->where('room_type_id', $item['room_type_id'])
                    ->where('stay_date', $item['stay_date'])
                    ->lockForUpdate()
                    ->first();

                if (!$invRow) {
                    throw new MissingInventoryException(
                        $e,
                        "Inventory row missing for room type {$item['room_type_id']} on {$item['stay_date']}"
                    );
                }
            }

            // 4. Apply inventory updates
            foreach ($released as $rtId => $dates) {
                if (empty($dates)) continue;
                if (self::$useReadModifyWrite) {
                    // NC3: read modify write in PHP
                    foreach ($dates as $d) {
                        $row = RoomInventory::where('room_type_id', $rtId)->where('stay_date', $d)->first();
                        $row->booked -= 1;
                        $row->version += 1;
                        $row->save();
                    }
                } else {
                    DB::table('room_inventory')
                        ->where('room_type_id', $rtId)
                        ->whereIn('stay_date', $dates)
                        ->update([
                            'booked' => DB::raw('booked - 1'),
                            'version' => DB::raw('version + 1'),
                            'updated_at' => now(),
                        ]);
                }
            }

            foreach ($taken as $rtId => $dates) {
                if (empty($dates)) continue;
                if (self::$useReadModifyWrite) {
                    // NC3: read modify write in PHP
                    foreach ($dates as $d) {
                        $row = RoomInventory::where('room_type_id', $rtId)->where('stay_date', $d)->first();
                        $row->booked += 1;
                        $row->version += 1;
                        $row->save();
                    }
                } else {
                    DB::table('room_inventory')
                        ->where('room_type_id', $rtId)
                        ->whereIn('stay_date', $dates)
                        ->update([
                            'booked' => DB::raw('booked + 1'),
                            'version' => DB::raw('version + 1'),
                            'updated_at' => now(),
                        ]);
                }
                foreach ($dates as $d) {
                    $touchedByRoomType[$rtId][$d] = true;
                }
            }

            foreach ($released as $rtId => $dates) {
                foreach ($dates as $d) {
                    $touchedByRoomType[$rtId][$d] = true;
                }
            }

            // 5. Update reservation record
            $status = $e->type === 'cancel' ? ReservationStatus::Cancelled : ReservationStatus::Confirmed;

            $res->update([
                'hotel_id' => $hotelId,
                'room_type_id' => $room->room_type_id,
                'rate_plan_id' => $rate->rate_plan_id,
                'external_version' => $e->externalVersion,
                'status' => $status,
                'check_in' => $e->checkIn ?: $res->check_in,
                'check_out' => $e->checkOut ?: $res->check_out,
                'guest_name' => $e->guestName ?: $res->guest_name,
                'adults' => $e->adults ?: $res->adults,
                'total_price' => $e->totalPrice ?: $res->total_price,
                'currency' => $e->currency ?: $res->currency,
            ]);

            // Replace reservation_nights
            DB::table('reservation_nights')->where('reservation_id', $res->id)->delete();
            if ($status === ReservationStatus::Confirmed) {
                $nightInserts = [];
                foreach ($newNights as $d) {
                    $nightInserts[] = [
                        'reservation_id' => $res->id,
                        'room_type_id' => $room->room_type_id,
                        'stay_date' => $d,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($nightInserts)) {
                    DB::table('reservation_nights')->insert($nightInserts);
                }
            }

            return IngestResult::Applied;
        }, 5);

        // Outside transaction: check for oversold or auto-resolve if cured, then trigger outbound sync
        if ($result === IngestResult::Applied && !empty($touchedByRoomType)) {
            foreach ($touchedByRoomType as $rtId => $datesMap) {
                foreach (array_keys($datesMap) as $d) {
                    $inv = DB::table('room_inventory')
                        ->where('room_type_id', (int) $rtId)
                        ->where('stay_date', (string) $d)
                        ->first();

                    if ($inv) {
                        $available = (int) $inv->capacity - (int) $inv->booked;
                        if ($available < 0) {
                            Alert::oversold($hotelId, (int) $rtId, (string) $d, $available);
                        } else {
                            Alert::resolveOversoldIfCured((int) $rtId, (string) $d);
                        }
                    }
                }
            }

            SyncKick::allChannels();
        }

        return $result;
    }
}
