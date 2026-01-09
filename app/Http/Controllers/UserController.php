<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Site;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of admin users
     */
    public function index()
    {
        $users = User::whereHas('role', function($query) {
            $query->where('name', 'admin');
        })->with(['role', 'site'])->get();

        $sites = Site::all();
        $roles = Role::where('name', 'admin')->get(); // Only admin role for this interface

        return view('users.index', compact('users', 'sites', 'roles'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        $sites = Site::all();
        $roles = Role::all();
        return view('users.create', compact('sites', 'roles'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'site_id' => 'nullable|exists:sites,id'
        ]);

        // Ensure only admins can create admin users
        if ($validated['role_id'] == Role::where('name', 'admin')->first()->id && !Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can create admin users.');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'site_id' => $validated['site_id']
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    /**
     * Display the specified user
     */
    public function show(User $user)
    {
        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing a user
     */
    public function edit(User $user)
    {
        // Prevent non-admins from editing admin users
        if ($user->isAdmin() && !Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can edit admin users.');
        }

        $sites = Site::all();
        $roles = Role::all();
        return view('users.edit', compact('user', 'sites', 'roles'));
    }

    /**
     * Update a user
     */
    public function update(Request $request, User $user)
    {
        // Prevent non-admins from editing admin users
        if ($user->isAdmin() && !Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can edit admin users.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'site_id' => 'nullable|exists:sites,id'
        ]);

        // Ensure only admins can update users to admin role
        if ($validated['role_id'] == Role::where('name', 'admin')->first()->id && !Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can assign admin role.');
        }

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
            'site_id' => $validated['site_id']
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Remove a user
     */
    public function destroy(User $user)
    {
        // Prevent users from deleting themselves or other admins if not admin
        if (($user->isAdmin() && !Auth::user()->isAdmin()) || $user->id === Auth::id()) {
            abort(403, 'You cannot delete this user.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}