<!-- Roles of one user, with assignment. -->
@extends('rbac::layout')

@section('title', 'User '.$user->getKey())

@section('content')
    <h1>User {{ $user->getKey() }} <span class="muted">({{ $user->rbacGuard() }})</span></h1>

    <table>
        <thead>
            <tr><th>Role</th><th>Expires</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($assigned as $role)
                <tr>
                    <td>{{ $role->slug }}</td>
                    <td>{{ $role->pivot->expires_at ?? 'never' }}</td>
                    <td style="text-align:right">
                        <form method="POST" action="{{ route('rbac.users.roles.destroy', [$user->getKey(), $role->slug, 'guard' => $guard]) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="danger">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No roles.</td></tr>
            @endforelse
        </tbody>
    </table>

    <form method="POST" action="{{ route('rbac.users.roles.store', [$user->getKey(), 'guard' => $guard]) }}" class="toolbar">
        @csrf
        <select name="role" required>
            @foreach ($available as $role)
                <option value="{{ $role->slug }}">{{ $role->slug }} (rank {{ $role->rank }})</option>
            @endforeach
        </select>
        <input type="datetime-local" name="expires_at" aria-label="Expires at (optional)">
        <button type="submit">Assign</button>
    </form>
    <p class="muted">Leave the date empty for a permanent role. You can only assign roles below your own rank.</p>
@endsection
