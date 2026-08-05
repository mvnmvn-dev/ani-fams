<?php

namespace App\Http\Controllers;

use App\Services\VisitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VisitorController extends Controller
{
    public function index(VisitorService $service)
    {
        return view('visitors.index', [
            'pageTitle' => 'Visitor Management',
            'visits' => $service->listVisits(),
            'visitors' => $service->visitors(),
            'employees' => $service->employees(),
            'areas' => $service->areas(),
            'canRegister' => Auth::user()->canPermission('visitor.register'),
            'canCheckIn' => Auth::user()->canPermission('visitor.checkin'),
            'canManage' => Auth::user()->canPermission('visitor.manage'),
        ]);
    }

    public function store(Request $request, VisitorService $service)
    {
        $result = $service->preRegister($request->all());

        return redirect()->route('visitors.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Visitor pre-registered successfully.' : $result['error']
        );
    }

    public function checkIn(string $id, VisitorService $service)
    {
        $result = $service->checkIn((int) $id);

        return redirect()->route('visitors.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Visitor checked in successfully.' : $result['error']
        );
    }

    public function checkOut(string $id, VisitorService $service)
    {
        $result = $service->checkOut((int) $id);

        return redirect()->route('visitors.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Visitor checked out successfully.' : $result['error']
        );
    }

    public function toggleWatchlist(Request $request, string $id, VisitorService $service)
    {
        $result = $service->toggleWatchlist((int) $id, $request->boolean('watchlist'));

        return redirect()->route('visitors.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Visitor watchlist status updated.' : $result['error']
        );
    }
}