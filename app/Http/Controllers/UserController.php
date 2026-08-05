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

    public function toggleStatus(Request $request, string $id, UserService $service)
    {
        $result = $service->toggleStatus((int) $id, (string) $request->input('current_status', 'active'));

        return $this->flashOrJson(
            $request,
            redirect()->route('users.index'),
            true,
            'User status updated.',
            [
                'action' => 'user.toggle',
                'id' => (int) $id,
                'status' => $result['status'] ?? null,
            ]
        );
    }
}
