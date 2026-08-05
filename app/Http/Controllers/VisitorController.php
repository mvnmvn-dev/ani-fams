<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsWithFlashOrJson;
use App\Services\VisitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VisitorController extends Controller
{
    use RespondsWithFlashOrJson;

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

    public function checkIn(Request $request, string $id, VisitorService $service)
    {
        $result = $service->checkIn((int) $id);
        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Visitor checked in successfully.' : (string) ($result['error'] ?? 'Unable to check in.');

        return $this->flashOrJson(
            $request,
            redirect()->route('visitors.index'),
            $success,
            $message,
            array_merge(['action' => 'visitor.checkin'], $result)
        );
    }

    public function checkOut(Request $request, string $id, VisitorService $service)
    {
        $result = $service->checkOut((int) $id);
        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Visitor checked out successfully.' : (string) ($result['error'] ?? 'Unable to check out.');

        return $this->flashOrJson(
            $request,
            redirect()->route('visitors.index'),
            $success,
            $message,
            array_merge(['action' => 'visitor.checkout'], $result)
        );
    }

    public function toggleWatchlist(Request $request, string $id, VisitorService $service)
    {
        $result = $service->toggleWatchlist((int) $id, $request->boolean('watchlist'));
        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Visitor watchlist status updated.' : (string) ($result['error'] ?? 'Unable to update watchlist.');

        return $this->flashOrJson(
            $request,
            redirect()->route('visitors.index'),
            $success,
            $message,
            array_merge(['action' => 'visitor.watchlist'], $result)
        );
    }
}
