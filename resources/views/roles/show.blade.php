<!-- One role and its grants. -->
@extends('rbac::layout')

@section('title', 'Role '.$role->slug)

@section('content')
    <h1>{{ $role->slug }} <span class="muted">({{ $role->guard }}, rank {{ $role->rank }})</span></h1>

    <table>
        <thead>
            <tr><th>Grant</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($role->grants as $grant)
                <tr>
                    <td>{{ $grant->permission.'@'.$grant->scope }}</td>
                    <td style="text-align:right">
                        <form method="POST" action="{{ route('rbac.roles.grants.destroy', $role) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="permission" value="{{ $grant->permission.'@'.$grant->scope }}">
                            <button type="submit" class="danger">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" class="muted">No grants yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <form method="POST" action="{{ route('rbac.roles.grants.store', $role) }}" class="toolbar">
        @csrf
        <input name="permission" placeholder="billing.invoice.show@team" required>
        <button type="submit">Add grant</button>
    </form>
    <p class="muted">Format: module.resource.verb@scope. You can only add grants you hold at that scope or wider.</p>
@endsection
