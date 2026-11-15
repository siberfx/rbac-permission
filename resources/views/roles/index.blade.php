<!-- Roles of one guard. -->
@extends('rbac::layout')

@section('title', 'Roles')

@section('content')
    <h1>Roles</h1>

    <div class="toolbar">
        @foreach ($guards as $name)
            <a href="{{ route('rbac.roles.index', ['guard' => $name]) }}" @if ($name === $guard) style="font-weight:700" @endif>{{ $name }}</a>
        @endforeach
    </div>

    <table>
        <thead>
            <tr><th>Role</th><th>Rank</th><th>Grants</th></tr>
        </thead>
        <tbody>
            @forelse ($roles as $role)
                <tr>
                    <td><a href="{{ route('rbac.roles.show', $role) }}">{{ $role->slug }}</a></td>
                    <td>{{ $role->rank }}</td>
                    <td>{{ $role->grants_count }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No roles in this guard. Run php artisan rbac:sync.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
