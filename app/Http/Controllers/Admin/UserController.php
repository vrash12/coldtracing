<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const SUPPORTED_ROLES = ['Administrator', 'Driver', 'Receiver'];

    /**
     * Only Administrator users can access this module.
     */
    private function authorizeAdmin(): void
    {
        if (!Auth::check() || !Auth::user()->isAdministrator()) {
            abort(403, 'Only administrators can access user management.');
        }
    }

    /**
     * Display users list with search.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $search = $request->input('search');
        $roleId = $request->input('role_id');
        $status = $request->input('status');

        $users = User::with('role')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($roleId, function ($query) use ($roleId) {
                $query->where('role_id', $roleId);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $roles = $this->supportedRoles();
        $accountStats = User::query()
            ->selectRaw('COUNT(*) AS total_accounts')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_accounts")
            ->first();
        $roleCounts = Role::query()
            ->whereIn('name', self::SUPPORTED_ROLES)
            ->withCount('users')
            ->pluck('users_count', 'name');

        $stats = [
            'total' => (int) $accountStats->total_accounts,
            'active' => (int) $accountStats->active_accounts,
            'drivers' => (int) $roleCounts->get('Driver', 0),
            'receivers' => (int) $roleCounts->get('Receiver', 0),
        ];

        return view('admin.users.index', compact(
            'users',
            'roles',
            'search',
            'roleId',
            'status',
            'stats'
        ));
    }

    /**
     * Show create user page.
     */
    public function create()
    {
        $this->authorizeAdmin();

        $roles = $this->supportedRoles();

        return view('admin.users.create', compact('roles'));
    }

    /**
     * Save new user.
     */
    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'role_id' => ['required', $this->supportedRoleRule()],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        User::create([
            'role_id' => $validated['role_id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User account created successfully.');
    }

    /**
     * Show user details.
     */
    public function show(User $user)
    {
        $this->authorizeAdmin();

        $user->load('role');

        return view('admin.users.show', compact('user'));
    }

    /**
     * Show edit user page.
     */
    public function edit(User $user)
    {
        $this->authorizeAdmin();

        $roles = $this->supportedRoles();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update user details.
     */
    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'role_id' => ['required', $this->supportedRoleRule()],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        $user->role_id = $validated['role_id'];
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->status = $validated['status'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()
            ->route('users.index')
            ->with('success', 'User account updated successfully.');
    }

    /**
     * Delete user.
     */
    public function destroy(User $user)
    {
        $this->authorizeAdmin();

        if ($user->id === Auth::id()) {
            return redirect()
                ->route('users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'User account deleted successfully.');
    }

    private function supportedRoles()
    {
        return Role::query()
            ->whereIn('name', self::SUPPORTED_ROLES)
            ->orderBy('name')
            ->get();
    }

    private function supportedRoleRule()
    {
        return Rule::exists('roles', 'id')
            ->where(fn ($query) => $query->whereIn('name', self::SUPPORTED_ROLES));
    }
}
