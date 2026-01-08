<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Site;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ClientUserController extends Controller
{
    // Middleware is applied in routes/web.php

    /**
     * Display a listing of client users
     */
    public function index()
    {
        $clients = User::whereHas('role', function($query) {
            $query->where('name', 'client');
        })->with('site')->get();

        $sites = Site::all();

        return view('client-users.index', compact('clients', 'sites'));
    }

    /**
     * Show the form for creating a new client user
     */
    public function create()
    {
        $sites = Site::all();
        return view('client-users.create', compact('sites'));
    }

    /**
     * Store a newly created client user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'site_id' => 'required|exists:sites,id'
        ]);

        $clientRole = Role::where('name', 'client')->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $clientRole->id,
            'site_id' => $validated['site_id']
        ]);

        return redirect()->route('client-users.index')->with('success', 'Client user created successfully.');
    }

    /**
     * Show the form for editing a client user
     */
    public function edit(User $client_user)
    {
        $sites = Site::all();
        return view('client-users.edit', compact('client_user', 'sites'));
    }

    /**
     * Update a client user
     */
    public function update(Request $request, User $client_user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$client_user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'site_id' => 'required|exists:sites,id'
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'site_id' => $validated['site_id']
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $client_user->update($updateData);

        return redirect()->route('client-users.index')->with('success', 'Client user updated successfully.');
    }

    /**
     * Remove a client user
     */
    public function destroy(User $client_user)
    {
        $client_user->delete();
        return redirect()->route('client-users.index')->with('success', 'Client user deleted successfully.');
    }

    /**
     * Show client management dashboard
     */
    public function dashboard()
    {
        $clients = User::whereHas('role', function($query) {
            $query->where('name', 'client');
        })->with('site')->get();

        $sites = Site::all();

        // Get statistics
        $totalClients = $clients->count();
        $totalSites = $sites->count();
        $clientsWithSites = $clients->whereNotNull('site_id')->count();

        return view('client-users.dashboard', compact('clients', 'sites', 'totalClients', 'totalSites', 'clientsWithSites'));
    }
}
