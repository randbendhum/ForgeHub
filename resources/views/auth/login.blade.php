@extends('layouts.app')

@section('title', 'Creator Login')

@section('content')
    <div class="auth-grid">
        <div class="panel form-stack">
            <div class="panel__header">
                <h1 class="heading-lg">CREATOR TERMINAL LOGIN</h1>
                <span class="kicker">AUTH</span>
            </div>

            <div class="panel panel--yellow" style="font-family: var(--font-mono); font-size: 0.78rem;">
                <strong>DEMO CREDENTIALS:</strong><br>
                EMAIL: <code>demo@forgemod.test</code> // PASSWORD: <code>password</code>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="form-stack">
                @csrf

                <div class="field">
                    <label for="email" class="field__label">EMAIL ADDRESS</label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ old('email', 'demo@forgemod.test') }}"
                           required
                           autofocus
                           class="field__input">
                    @error('email')
                        <span class="field__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="field">
                    <label for="password" class="field__label">PASSWORD</label>
                    <input type="password"
                           id="password"
                           name="password"
                           value="password"
                           required
                           class="field__input">
                    @error('password')
                        <span class="field__error">{{ $message }}</span>
                    @enderror
                </div>

                <label style="display: flex; align-items: center; gap: 0.5rem; font-family: var(--font-mono); font-size: 0.8rem; font-weight: 700;">
                    <input type="checkbox" name="remember" value="1" style="width: 1.1rem; height: 1.1rem;">
                    KEEP SESSION ACTIVE ON THIS DEVICE
                </label>

                <button type="submit" class="btn btn--block">ENTER FORGEMOD HUB</button>
            </form>

            <div style="text-align: center; font-family: var(--font-mono); font-size: 0.8rem; padding-top: 0.5rem; border-top: 3px solid #000000;">
                NEED A CREATOR ACCOUNT?
                <a href="{{ route('register') }}" style="font-weight: 800; text-decoration: underline;">REGISTER NOW</a>
            </div>
        </div>
    </div>
@endsection
