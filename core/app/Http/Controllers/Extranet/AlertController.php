<?php

declare(strict_types=1);

namespace App\Http\Controllers\Extranet;

use App\Enums\AlertType;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $hotel = $this->getActiveHotel() ?? Hotel::first();
        $filter = $request->query('filter', 'open'); // open, critical, warning, resolved
        $type = $request->query('type');
        $search = $request->query('search');

        $query = Alert::orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($filter === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($filter === 'critical') {
            $query->whereNull('resolved_at')->where('type', AlertType::Oversold);
        } elseif ($filter === 'warning') {
            $query->whereNull('resolved_at')->where('type', '!=', AlertType::Oversold);
        } elseif ($filter === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        if ($type) {
            $query->where('type', $type);
        }

        $alerts = $query->paginate(20)->withQueryString();

        // Counts
        $openCount = Alert::whereNull('resolved_at')->count();
        $criticalCount = Alert::whereNull('resolved_at')->where('type', AlertType::Oversold)->count();
        $warningCount = Alert::whereNull('resolved_at')->where('type', '!=', AlertType::Oversold)->count();
        $resolvedCount = Alert::whereNotNull('resolved_at')->count();

        return view('extranet.alerts', [
            'hotel' => $hotel,
            'alerts' => $alerts,
            'filter' => $filter,
            'type' => $type,
            'search' => $search,
            'openCount' => $openCount,
            'criticalCount' => $criticalCount,
            'warningCount' => $warningCount,
            'resolvedCount' => $resolvedCount,
        ]);
    }

    public function acknowledge(int $id): RedirectResponse
    {
        $alert = Alert::findOrFail($id);
        $alert->update(['acknowledged_at' => now()]);

        return back()->with('success', "Alert #{$id} acknowledged.");
    }

    public function resolve(int $id): RedirectResponse
    {
        $alert = Alert::findOrFail($id);
        $alert->update(['resolved_at' => now()]);

        return back()->with('success', "Alert #{$id} resolved.");
    }
}
