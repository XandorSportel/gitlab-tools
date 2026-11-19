@extends('layouts.app')
@section('title','Settings — GitLab Tools')
@section('header','Settings')
@section('content')
<div class="page-head"><div><h2>Application settings</h2><p>Runtime configuration for this GitLab Tools installation.</p></div></div>
<section class="card" style="max-width:760px"><h3 class="section-title">GitLab connection</h3><p class="section-copy">These values are managed through the server environment and cannot be changed here.</p><div class="settings-list"><div class="setting-row"><strong>GitLab URL</strong><span>{{ $gitlab['url'] }}</span></div><div class="setting-row"><strong>Project</strong><span>{{ $gitlab['project'] ?: 'Not configured' }}</span></div><div class="setting-row"><strong>Access token</strong><span class="{{ $gitlab['token_configured'] ? 'ok':'bad' }}">{{ $gitlab['token_configured'] ? 'Configured':'Not configured' }}</span></div></div></section>
@endsection
