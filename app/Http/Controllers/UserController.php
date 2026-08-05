<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
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
        $service->toggleStatus((int) $id, (string) $request->input('current_status', 'active'));
        return redirect()->route('users.index')->with('flash_success', 'User status updated.');
    }
}