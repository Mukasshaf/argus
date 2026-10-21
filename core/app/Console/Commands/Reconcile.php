<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\RoomType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Reconcile extends Command
{
    protected $signature = 'staybridge:reconcile';
    protected $description = 'Asserts booked == count(reservation_nights) for each room type and date; raises drift alerts';

    public function handle(): int
    {
        $this->info("Running inventory ledger reconciliation...");

        // Query each room_inventory row and compare booked with count of reservation_nights
        $records = DB::select(
            "SELECT ri.room_type_id, ri.stay_date, ri.booked,
                    COALESCE(rn.held_count, 0) AS held_count
             FROM room_inventory ri
             LEFT JOIN (
                 SELECT room_type_id, stay_date, COUNT(*) AS held_count
                 FROM reservation_nights
                 GROUP BY room_type_id, stay_date
             ) rn ON rn.room_type_id = ri.room_type_id AND rn.stay_date = ri.stay_date
             WHERE ri.booked != COALESCE(rn.held_count, 0)
             ORDER BY ri.room_type_id, ri.stay_date"
        );

        if (empty($records)) {
            $this->info("All records consistent: booked == count(reservation_nights). 0 drift detected.");
            return self::SUCCESS;
        }

        $this->error("Drift detected in " . count($records) . " records!");

        foreach ($records as $r) {
            $this->warn("Diff: RoomType {$r->room_type_id} on {$r->stay_date}: Inventory.booked={$r->booked}, Nights.count={$r->held_count}");
            // Raise drift alert (Never auto fix)
            Alert::drift((int) $r->room_type_id, (string) $r->stay_date, (int) $r->booked, (int) $r->held_count);
        }

        return self::FAILURE;
    }
}
