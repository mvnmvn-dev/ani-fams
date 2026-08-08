<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $allPermissions = Permission::orderBy('code')->get();

        return view('settings.roles.index', [
            'pageTitle' => 'Role Management',
            'roles' => $roles,
            'allPermissions' => $allPermissions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|alpha_dash|unique:roles,code',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        DB::transaction(function () use ($validated) {
            $role = Role::create([
                'name' => $validated['name'],
                'code' => strtolower($validated['code']),
                'description' => $validated['description'] ?? '',
            ]);

            if (! empty($validated['permissions'])) {
                $role->permissions()->attach($validated['permissions']);
            }
        });

        return redirect()
            ->route('roles.index')
            ->with('flash_success', 'Role created successfully.');
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('roles', 'code')->ignore($role->id),
            ],
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        DB::transaction(function () use ($request, $role, $validated) {
            $role->update([
                'name' => $validated['name'],
                'code' => strtolower($validated['code']),
                'description' => $validated['description'] ?? '',
            ]);

            $role->permissions()->sync($validated['permissions'] ?? []);
        });

        return redirect()
            ->route('roles.index')
            ->with('flash_success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        if (in_array($role->code, ['admin', 'staff', 'employee'], true)) {
            return redirect()
                ->route('roles.index')
                ->with('flash_error', 'Built-in system roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('roles.index')
                ->with('flash_error', 'Cannot delete a role that is still assigned to users.');
        }

        $role->permissions()->detach();
        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('flash_success', 'Role deleted successfully.');
    }
}
