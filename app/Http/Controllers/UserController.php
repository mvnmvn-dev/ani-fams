<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsWithFlashOrJson;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use RespondsWithFlashOrJson;

    public function index(UserService $service)
    {
        return view('users.index', [
            'pageTitle' => 'User Management',
            'users' => $service->list(),
            'roles' => $service->roles(),
        ]);
    }

    public function store(Request $request, UserService $service)
    {
        $result = $service->createUser($request->all());

        return redirect()->route('users.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'User created successfully.' : $result['error']
        );
    }

    public function import(Request $request, UserService $service)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $result = $service->importCsv($request->file('csv_file'));
        if (! ($result['success'] ?? false)) {
            return redirect()->route('users.index')->with('flash_error', $result['error'] ?? 'Import failed.');
        }

        $msg = 'Imported ' . ($result['imported'] ?? 0) . ' user(s)';
        if (($result['skipped'] ?? 0) > 0) {
            $msg .= ', skipped ' . $result['skipped'];
        }
        $msg .= '.';

        return redirect()->route('users.index')->with('flash_success', $msg);
    }

    public function destroy(string $id, UserService $service)
    {
        $result = $service->softDelete((int) $id);

        return redirect()->route('users.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'User deleted.' : ($result['error'] ?? 'Unable to delete.')
        );
    }

    public function toggleStatus(Request $request, string $id, UserService $service)
    {
        $result = $service->toggleStatus((int) $id, (string) $request->input('current_status', 'active'));
        $success = (bool) ($result['success'] ?? false);

        return $this->flashOrJson(
            $request,
            redirect()->route('users.index'),
            $success,
            $success ? 'User status updated.' : (string) ($result['error'] ?? 'Unable to update user status.'),
            [
                'action' => 'user.toggle',
                'id' => (int) $id,
                'status' => $result['status'] ?? null,
            ]
        );
    }
}
