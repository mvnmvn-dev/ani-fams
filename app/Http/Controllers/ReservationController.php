<?php

namespace App\Http\Controllers;

use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    public function index(ReservationService $service)
    {
        return view('reservations.index', [
            'pageTitle' => 'Facilities Reservation',
            'reservations' => $service->listForCurrentUser(),
            'facilities' => $service->facilities(),
            'canApprove' => Auth::user()->canPermission('reservation.approve'),
        ]);
    }

    public function store(Request $request, ReservationService $service)
    {
        $result = $service->submitRequest($request->all());

        return redirect()->route('reservations.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Reservation request submitted for approval.' : $result['error']
        );
    }

    public function decide(Request $request, string $id, ReservationService $service)
    {
        $decision = (string) $request->input('decision', '');
        $result = $service->decide((int) $id, $decision);

        return redirect()->route('reservations.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Reservation ' . $decision . '.' : $result['error']
        );
    }
}