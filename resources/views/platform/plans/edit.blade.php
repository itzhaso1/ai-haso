@extends('platform.layout')

@section('content')
    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4">
            @include('platform.partials.nav')
            @include('partials.flash')
            <form method="POST" action="{{ route('platform.plans.update', $plan) }}" class="rounded-xl border bg-white p-6 space-y-4">
                @csrf @method('PUT')
                @include('platform.plans.form', ['plan' => $plan, 'featuresJson' => $featuresJson, 'limitsJson' => $limitsJson])
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white">تحديث</button>
            </form>
        </div>
    </div>
@endsection
