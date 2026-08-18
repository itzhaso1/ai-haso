@extends('platform.layout')

@section('content')
    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4">
            @include('platform.partials.nav')
            @include('partials.flash')
            <div class="mb-4 text-left">
                <a href="{{ route('platform.plans.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white">إضافة خطة</a>
            </div>
            <div class="overflow-x-auto rounded-xl border bg-white">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right">الاسم</th>
                            <th class="px-4 py-3 text-right">النوع</th>
                            <th class="px-4 py-3 text-right">السعر</th>
                            <th class="px-4 py-3 text-right">الحالة</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($plans as $plan)
                            <tr>
                                <td class="px-4 py-3">{{ $plan->name }}</td>
                                <td class="px-4 py-3">{{ $plan->workspace_type }}</td>
                                <td class="px-4 py-3">{{ number_format((float)$plan->price, 2) }} {{ $plan->currency }}</td>
                                <td class="px-4 py-3">{{ $plan->is_active ? 'نشط' : 'غير نشط' }}</td>
                                <td class="px-4 py-3 text-left">
                                    <a href="{{ route('platform.plans.edit', $plan) }}" class="text-blue-600">تعديل</a>
                                    <form method="POST" action="{{ route('platform.plans.destroy', $plan) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button class="mr-3 text-red-600" onclick="return confirm('حذف الخطة؟')">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">لا توجد خطط.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $plans->links() }}</div>
        </div>
    </div>
@endsection
