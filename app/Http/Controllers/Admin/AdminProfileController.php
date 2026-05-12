<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('admin/settings', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($request->user()?->id),
            ],
        ]);

        $request->user()?->update($validated);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        if (! Hash::check($validated['current_password'], (string) $request->user()?->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $request->user()?->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated.');
    }
}
