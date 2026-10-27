<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Developer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-zA-Z0-9_-]+$/', 'unique:developers,username'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:developers,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'bio' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'CREATOR NAME IS REQUIRED.',
            'username.required' => 'TECHNICAL USERNAME IS REQUIRED.',
            'username.regex' => 'USERNAME MAY ONLY CONTAIN LETTERS, DIGITS, HYPHENS, AND UNDERSCORES.',
            'username.unique' => 'THIS USERNAME IS ALREADY REGISTERED.',
            'email.unique' => 'THIS EMAIL IS ALREADY ASSOCIATED WITH AN ACCOUNT.',
            'password.confirmed' => 'PASSWORD CONFIRMATION DOES NOT MATCH.',
            'password.min' => 'PASSWORD MUST BE AT LEAST 8 CHARACTERS LONG.',
        ]);

        $developer = Developer::query()->create([
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'bio' => $validated['bio'] ?? null,
        ]);

        Auth::login($developer);
        $request->session()->regenerate();

        return redirect()->route('upload.create')
            ->with('status', sprintf('CREATOR ACCOUNT "@%s" ACTIVATED SUCCESSFULLY.', $developer->username));
    }
}
