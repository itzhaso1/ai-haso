@extends('platform.layout')

@section('content')
    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4">
            @include('platform.partials.nav')
            @include('partials.flash')
            <form method="POST" action="{{ route('platform.plans.store') }}" class="rounded-xl border bg-white p-6 space-y-4">
                @csrf
                @include('platform.plans.form')
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white">حفظ</button>
            </form>
        </div>
    </div>
@endsection
