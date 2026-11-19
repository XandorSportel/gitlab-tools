@extends('layouts.guest')
@section('title','Create account — GitLab Tools')
@section('content')
<section class="panel"><h1>Create account</h1><p>Join your team's GitLab Tools workspace.</p>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('register.store') }}">@csrf
<label for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" required autofocus>
<label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" required>
<label for="password">Password</label><input id="password" name="password" type="password" required>
<label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required>
<button type="submit">Create account</button></form></section><div class="below"><a href="{{ route('login') }}">Back to sign in</a></div>
@endsection
