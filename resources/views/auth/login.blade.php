@extends('layouts.guest')
@section('title', 'Sign in — GitLab Burndown')
@section('content')
    <section class="panel"><h1>Sign in</h1><p>Enter your details to continue.</p>
        @if($errors->any()) <div class="error">{{ $errors->first() }}</div> @endif
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            <label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>
            <label style="display:flex;align-items:center;gap:8px;font-weight:400"><input style="width:auto" type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <button type="submit">Sign in</button>
        </form>
    </section>@if(config('app.allow_registration'))<div class="below">No account? <a href="{{ route('register') }}">Create one</a></div>@endif
@endsection
