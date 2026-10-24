<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Hotel;

abstract class Controller
{
    protected function getActiveHotel(): ?Hotel
    {
        $hotelId = session('active_hotel_id');
        if ($hotelId) {
            $hotel = Hotel::find($hotelId);
            if ($hotel) {
                return $hotel;
            }
        }

        $hotel = Hotel::first();
        if ($hotel && !session()->has('active_hotel_id')) {
            session(['active_hotel_id' => $hotel->id]);
        }

        return $hotel;
    }
}
