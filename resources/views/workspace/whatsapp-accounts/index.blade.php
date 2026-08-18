<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold">حسابات واتساب</h2></x-slot>
    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4">
            @include('workspace.partials.nav')
            @include('partials.flash')
            <div class="mb-4 text-left">
                <a href="{{ route('workspace.whatsapp-accounts.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white">إضافة حساب</a>
            </div>
            <div class="overflow-x-auto rounded-xl border bg-white">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right">الاسم</th>
                            <th class="px-4 py-3 text-right">Business ID</th>
                            <th class="px-4 py-3 text-right">الحالة</th>
                            <th class="px-4 py-3 text-right">الأرقام</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($accounts as $account)
                            <tr>
                                <td class="px-4 py-3">{{ $account->display_name }}</td>
                                <td class="px-4 py-3">{{ $account->business_account_id }}</td>
                                <td class="px-4 py-3">{{ $account->status }}</td>
                                <td class="px-4 py-3">{{ $account->phoneNumbers->count() }}</td>
                                <td class="px-4 py-3 text-left">
                                    <a href="{{ route('workspace.whatsapp-accounts.edit', $account) }}" class="text-blue-600">تعديل</a>
                                    <form method="POST" action="{{ route('workspace.whatsapp-accounts.destroy', $account) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button class="mr-3 text-red-600" onclick="return confirm('تأكيد الحذف؟')">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">لا توجد حسابات.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $accounts->links() }}</div>
        </div>
    </div>
</x-app-layout>
