@extends('platform.layout')
@section('title', 'Clinic operations')
@section('content')
<div class="page-heading">
    <div><span class="eyebrow">PLATFORM OPERATIONS</span><h1>Clinic registry</h1><p>Clinic lifecycle and SaaS billing status. Lifecycle changes are recorded in the platform audit.</p></div>
</div>
<section class="panel" aria-label="Clinic operational metadata">
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th scope="col">Clinic</th><th scope="col">Subdomain</th><th scope="col">Lifecycle</th><th scope="col">Billing</th><th scope="col">Update lifecycle</th></tr></thead>
            <tbody>
            @forelse($tenants as $tenant)
                <tr>
                    <td><strong>{{ $tenant->name }}</strong></td>
                    <td>{{ $tenant->subdomain }}</td>
                    <td><span class="status">{{ ucfirst($tenant->lifecycle_status) }}</span></td>
                    <td>{{ ucfirst($tenant->billing_status) }}</td>
                    <td>
                        <form method="POST" action="{{ route('platform.lifecycle', $tenant->id) }}">
                            @csrf
                            <label for="lifecycle-{{ $tenant->id }}" class="sr-only">Lifecycle for {{ $tenant->name }}</label>
                            <select id="lifecycle-{{ $tenant->id }}" name="lifecycle_status" required>
                                @foreach(['pending', 'active', 'suspended', 'closed'] as $status)
                                    <option value="{{ $status }}" @selected($tenant->lifecycle_status === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            <button class="button secondary">Save</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No clinics registered.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
{{ $tenants->links() }}
@endsection
