@extends('layouts.app')

@section('content')
<div class="space-y-6 animate-fade-in-up">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="page-header-title">Role Management</h1>
            <p class="type-meta mt-1">Configure user roles and granular permissions</p>
        </div>
        <button type="button" onclick="openCreateRoleModal()" class="btn-primary">
            + Create Role
        </button>
    </div>

    @if ($errors->any())
        <div class="px-4 py-3 rounded-lg bg-red-50 text-red-700 border border-red-200 text-sm">
            <p class="font-semibold mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white border border-ink/10 rounded-2xl overflow-hidden shadow-sm" data-filter-root>
        <div class="module-table-wrap">
        <table class="module-table w-full text-left text-sm">
            <thead class="bg-gray-50/50 text-ink/40 text-[11px] font-bold uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4">Role Name</th>
                    <th class="px-6 py-4">Code</th>
                    <th class="px-6 py-4">Description</th>
                    <th class="px-6 py-4">Permissions</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink/5">
                @forelse($roles as $role)
                    <tr class="hover:bg-gray-50/30 transition-colors" data-filter-item
                        data-filter-search="{{ strtolower($role->name.' '.$role->code.' '.$role->description) }}">
                        <td class="px-6 py-4 font-semibold text-ink">{{ $role->name }}</td>
                        <td class="px-6 py-4"><span class="px-2 py-1 bg-brand-50 text-brand-700 rounded-md text-xs font-mono font-bold">{{ $role->code }}</span></td>
                        <td class="px-6 py-4 text-ink/60">{{ $role->description }}</td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1">
                                @php
                                    $rolePerms = $role->permissions->pluck('code')->take(3);
                                    $moreCount = $role->permissions->count() - 3;
                                @endphp
                                @foreach($rolePerms as $p)
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-ink/60 border border-ink/10">{{ $p }}</span>
                                @endforeach
                                @if($moreCount > 0)
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-ink/60 border border-ink/10">+{{ $moreCount }} more</span>
                                @endif
                                @if($role->permissions->count() === 0)
                                    <span class="text-xs text-ink/40">No permissions</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <button type="button"
                                    class="text-brand-600 hover:text-brand-800 font-semibold text-xs mr-3"
                                    data-role-edit
                                    data-role='@json([
                                        "id" => $role->id,
                                        "name" => $role->name,
                                        "code" => $role->code,
                                        "description" => $role->description,
                                        "permissions" => $role->permissions->pluck("id")->map(fn ($id) => (int) $id)->values(),
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'>
                                Edit
                            </button>
                            @if (! in_array($role->code, ['admin', 'staff', 'employee'], true))
                                <form action="{{ url('/settings/roles/'.$role->id) }}" method="POST" class="inline-block data-role-destroy"
                                      data-action-path="settings/roles/{{ $role->id }}"
                                      onsubmit="this.action = window.appUrl(this.getAttribute('data-action-path')); return confirm('Delete this role?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 font-semibold text-xs">Delete</button>
                                </form>
                            @else
                                <span class="text-[10px] text-ink/35 uppercase tracking-wider font-bold">System</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-ink/40">No roles found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

<div id="createRoleModal" class="modal-overlay hidden" hidden>
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-ink/10 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-sans text-lg font-bold text-ink" id="roleModalTitle">Create Role</h3>
            <button type="button" onclick="closeRoleModal()" class="text-ink/40 hover:text-ink/60 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="roleForm" action="{{ route('roles.store') }}" method="POST" class="p-6">
            @csrf
            <input type="hidden" name="_method" id="roleMethod" value="POST">

            <div class="space-y-4 mb-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Role Name</label>
                        <input type="text" name="name" id="roleName" required maxlength="100"
                               class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Role Code</label>
                        <input type="text" name="code" id="roleCode" required maxlength="50" pattern="[A-Za-z0-9_-]+"
                               title="Letters, numbers, dashes, and underscores only"
                               class="w-full border border-ink/15 rounded-lg p-2.5 text-sm font-mono bg-sand/10 focus:border-brand-500 outline-none">
                    </div>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Description</label>
                    <input type="text" name="description" id="roleDesc" maxlength="255"
                           class="w-full border border-ink/15 rounded-lg p-2.5 text-sm bg-sand/10 focus:border-brand-500 outline-none">
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink/60">Permissions</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-64 overflow-y-auto border border-ink/10 rounded-lg p-3 bg-gray-50/30">
                    @foreach($allPermissions as $perm)
                        <label class="flex items-start gap-2 p-2 hover:bg-white rounded border border-transparent hover:border-ink/5 transition cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="mt-1 perm-checkbox">
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-ink">{{ $perm->code }}</div>
                                <div class="text-[10px] text-ink/50 truncate">{{ $perm->description }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="pt-6 mt-4 border-t border-ink/10 flex justify-end gap-3">
                <button type="button" onclick="closeRoleModal()" class="px-4 py-2.5 rounded-lg border border-ink/10 hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-600 text-white font-semibold text-sm transition" id="roleSubmitBtn">Create Role</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var storePath = 'settings/roles';

    window.openCreateRoleModal = function () {
        closeRoleModal(true);
        openModal('createRoleModal');
    };

    window.closeRoleModal = function (skipClose) {
        var form = document.getElementById('roleForm');
        form.reset();
        document.getElementById('roleMethod').value = 'POST';
        form.action = window.appUrl(storePath);
        document.getElementById('roleModalTitle').innerText = 'Create Role';
        document.getElementById('roleSubmitBtn').innerText = 'Create Role';
        document.querySelectorAll('.perm-checkbox').forEach(function (cb) { cb.checked = false; });
        if (!skipClose) closeModal('createRoleModal');
    };

    window.editRole = function (role) {
        document.getElementById('roleForm').action = window.appUrl(storePath + '/' + role.id);
        document.getElementById('roleMethod').value = 'PUT';
        document.getElementById('roleModalTitle').innerText = 'Edit Role';
        document.getElementById('roleSubmitBtn').innerText = 'Update Role';

        document.getElementById('roleName').value = role.name || '';
        document.getElementById('roleCode').value = role.code || '';
        document.getElementById('roleDesc').value = role.description || '';

        var selected = (role.permissions || []).map(Number);
        document.querySelectorAll('.perm-checkbox').forEach(function (cb) {
            cb.checked = selected.indexOf(parseInt(cb.value, 10)) !== -1;
        });

        openModal('createRoleModal');
    };

    document.querySelectorAll('[data-role-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try {
                editRole(JSON.parse(btn.getAttribute('data-role')));
            } catch (e) {
                console.error(e);
                if (typeof showToast === 'function') showToast('error', 'Unable to open role editor.');
            }
        });
    });

    // Ensure create form starts with a subdirectory-safe action.
    document.getElementById('roleForm').action = window.appUrl(storePath);

    @if ($errors->any())
        openModal('createRoleModal');
    @endif
})();
</script>
@endsection
