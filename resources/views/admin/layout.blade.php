@extends('layouts.app')

@section('title', 'Admin — GitLab Tools')
@section('header', 'Admin')

@section('content')
    @if (session('status'))
        <div class="alert success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert" role="alert">{{ $errors->first() }}</div>
    @endif

    @yield('admin-content')
@endsection
