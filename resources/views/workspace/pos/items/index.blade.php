@extends('layouts.pos', ['pageTitle' => 'إدارة الأصناف'])

@section('content')
    <div class="grid gap-4 xl:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">أصناف الكاشير</h2>
                <form method="GET" class="flex items-center gap-2">
                    <input name="search" value="{{ request('search') }}" placeholder="بحث..." class="rounded-lg border-slate-300 text-sm" />
                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">بحث</button>
                </form>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-right">الصنف</th>
                            <th class="px-3 py-2 text-right">النوع</th>
                            <th class="px-3 py-2 text-right">السعر</th>
                            <th class="px-3 py-2 text-right">الحالة</th>
                            <th class="px-3 py-2 text-right">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr class="border-t border-slate-200">
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        @if($item->image_path)
                                            <img src="{{ asset('storage/'.$item->image_path) }}" alt="{{ $item->name }}" class="h-10 w-10 rounded object-cover">
                                        @endif
                                        <span class="font-semibold">{{ $item->name }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2">{{ $item->item_type }}</td>
                                <td class="px-3 py-2">{{ number_format((float) $item->price, 2) }} {{ $item->currency }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full px-2 py-1 text-xs {{ $item->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                        {{ $item->is_active ? 'مفعل' : 'متوقف' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <details>
                                        <summary class="cursor-pointer text-xs font-semibold text-slate-700">تعديل</summary>
                                        <div class="mt-2 space-y-2 rounded-lg border border-slate-200 p-2">
                                        <form method="POST" action="{{ route('workspace.pos.items.update', $item) }}" enctype="multipart/form-data" class="space-y-2">
                                            @csrf
                                            @method('PUT')
                                            <input name="name" value="{{ $item->name }}" class="w-full rounded border-slate-300 text-xs" />
                                            <input name="item_type" value="{{ $item->item_type }}" class="w-full rounded border-slate-300 text-xs" />
                                            <input type="number" name="price" step="0.01" min="0" value="{{ $item->price }}" class="w-full rounded border-slate-300 text-xs" />
                                            <input name="currency" value="{{ $item->currency }}" class="w-full rounded border-slate-300 text-xs" />
                                            <input type="number" name="sort_order" min="0" value="{{ $item->sort_order }}" class="w-full rounded border-slate-300 text-xs" />
                                            <label class="inline-flex items-center gap-2 text-xs">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" value="1" @checked($item->is_active)>
                                                مفعل
                                            </label>
                                            <input type="file" name="image_file" class="w-full rounded border-slate-300 text-xs" />
                                            <label class="inline-flex items-center gap-2 text-xs">
                                                <input type="checkbox" name="remove_image" value="1">
                                                حذف الصورة
                                            </label>
                                            <button class="rounded bg-slate-900 px-2 py-1 text-xs text-white">حفظ</button>
                                        </form>
                                        <form method="POST" action="{{ route('workspace.pos.items.destroy', $item) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded bg-rose-600 px-2 py-1 text-xs text-white">حذف</button>
                                        </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">لا توجد أصناف حتى الآن.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $items->links() }}</div>
        </section>

        <aside class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-base font-bold text-slate-900">إضافة صنف جديد</h3>
            <form method="POST" action="{{ route('workspace.pos.items.store') }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                @csrf
                <input name="name" required placeholder="اسم الصنف" class="w-full rounded-lg border-slate-300 text-sm" />
                <input name="item_type" placeholder="النوع (مثال: مشروبات)" class="w-full rounded-lg border-slate-300 text-sm" list="pos-item-types" />
                <datalist id="pos-item-types">
                    @foreach($types as $type)
                        <option value="{{ $type }}"></option>
                    @endforeach
                </datalist>
                <input type="number" step="0.01" min="0" name="price" required placeholder="السعر" class="w-full rounded-lg border-slate-300 text-sm" />
                <input name="currency" value="USD" class="w-full rounded-lg border-slate-300 text-sm" />
                <input type="number" min="0" name="sort_order" value="0" class="w-full rounded-lg border-slate-300 text-sm" placeholder="الترتيب" />
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked>
                    الصنف مفعل
                </label>
                <input type="file" name="image_file" class="w-full rounded-lg border-slate-300 text-sm" />
                <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">إضافة الصنف</button>
            </form>
        </aside>
    </div>
@endsection
