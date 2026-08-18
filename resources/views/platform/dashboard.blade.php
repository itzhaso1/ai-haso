@extends('platform.layout')

@section('content')
    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4">
            @include('platform.partials.nav')
            @include('partials.flash')
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border bg-white p-5"><p class="text-sm text-gray-500">Users</p><p class="text-2xl font-bold">{{ $stats['users'] }}</p></div>
                <div class="rounded-xl border bg-white p-5"><p class="text-sm text-gray-500">Workspaces</p><p class="text-2xl font-bold">{{ $stats['workspaces'] }}</p></div>
                <div class="rounded-xl border bg-white p-5"><p class="text-sm text-gray-500">Plans</p><p class="text-2xl font-bold">{{ $stats['plans'] }}</p></div>
                <div class="rounded-xl border bg-white p-5"><p class="text-sm text-gray-500">Subscriptions</p><p class="text-2xl font-bold">{{ $stats['subscriptions'] }}</p></div>
                <div class="rounded-xl border bg-white p-5"><p class="text-sm text-gray-500">Orders</p><p class="text-2xl font-bold">{{ $stats['orders'] }}</p></div>
                <div class="rounded-xl border bg-white p-5"><p class="text-sm text-gray-500">Paid Payments</p><p class="text-2xl font-bold">{{ $stats['payments_paid'] }}</p></div>
            </div>
        </div>
    </div>
@endsection
