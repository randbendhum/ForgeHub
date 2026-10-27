@extends('layouts.app')

@section('title', 'Register Creator Account')

@section('content')
    <div class="auth-grid">
        <div class="panel form-stack">
            <div class="panel__header">
                <h1 class="heading-lg">REGISTER NEW CREATOR</h1>
                <span class="kicker">DEV ACCOUNT</span>
            </div>

            <form method="POST" action="{{ route('register.store') }}" class="form-stack">
                @csrf

                <div class="field">
                    <label for="name" class="field__label">NAME OR MODDING TEAM</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required class="field__input"
                           placeholder="e.g. AgroTech Modding">
                    @error('name')
                        <span class="field__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="field">
                    <label for="username" class="field__label">TECHNICAL USERNAME (A-Z, 0-9, _)</label>
                    <input type="text" id="username" name="username" value="{{ old('username') }}" required class="field__input"
                           placeholder="e.g. agrotech_dev">
                    @error('username')
                        <span class="field__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="field">
                    <label for="email" class="field__label">EMAIL</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required class="field__input">
                    @error('email')
                        <span class="field__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="field">
                    <label for="bio" class="field__label">TECHNICAL BIO (OPTIONAL)</label>
                    <textarea id="bio" name="bio" rows="2" class="field__textarea"
                              placeholder="Games and specialties (e.g. FS25 Lua, Minecraft Fabric)...">{{ old('bio') }}</textarea>
                </div>

                <div class="field">
                    <label for="password" class="field__label">PASSWORD (MIN 8 CHARACTERS)</label>
                    <input type="password" id="password" name="password" required class="field__input">
                    @error('password')
                        <span class="field__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="field">
                    <label for="password_confirmation" class="field__label">CONFIRM PASSWORD</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required class="field__input">
                </div>

                <button type="submit" class="btn btn--block">CREATE ACCOUNT &amp; OPEN DROP ZONE</button>
            </form>
        </div>
    </div>
@endsection
