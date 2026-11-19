@extends('layouts.app')
@section('title','Profile — GitLab Tools')
@section('header','Profile')
@section('content')
<div class="page-head"><div><h2>Your profile</h2><p>Manage your account details and password.</p></div></div>
@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
<div style="display:grid;gap:18px;max-width:760px">
<section class="card"><h3 class="section-title">Account details</h3><p class="section-copy">Update your name and email address.</p><form class="form-grid" method="post" action="{{ route('profile.update') }}">@csrf @method('PATCH')<div><label for="name">Name</label><input id="name" name="name" value="{{ old('name',auth()->user()->name) }}" required></div><div><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email',auth()->user()->email) }}" required></div><div class="full"><button class="primary">Save profile</button></div></form></section>
<section class="card"><h3 class="section-title">Password</h3><p class="section-copy">Choose a password of at least 10 characters.</p><form class="form-grid" method="post" action="{{ route('profile.password') }}">@csrf @method('PUT')<div class="full"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required></div><div><label for="password">New password</label><input id="password" name="password" type="password" required></div><div><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required></div><div class="full"><button class="primary">Update password</button></div></form></section>
</div>@endsection
