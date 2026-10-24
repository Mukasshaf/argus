<?php

declare(strict_types=1);

namespace App\Http\Controllers\Extranet;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function switch(int $id, Request $request): RedirectResponse
    {
        $hotel = Hotel::findOrFail($id);
        $request->session()->put('active_hotel_id', $hotel->id);

        return back()->with('status', "Switched active property to {$hotel->name}");
    }
}
