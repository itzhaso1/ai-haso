<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use InteractsWithWorkspace;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->when($request->string('search')->toString(), function ($query, $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('workspace.customers.index', compact('customers'));
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('workspace.customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $payload = $request->validated();
        $payload['metadata'] = $this->parseJsonField($request, 'metadata_json');

        Customer::query()->create($payload);

        return redirect()->route('workspace.customers.index')->with('success', 'تم إنشاء العميل.');
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('workspace.customers.edit', [
            'customer' => $customer,
            'metadataJson' => json_encode($customer->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $payload = $request->validated();
        $payload['metadata'] = $this->parseJsonField($request, 'metadata_json', $customer->metadata ?? []);
        $customer->update($payload);

        return redirect()->route('workspace.customers.index')->with('success', 'تم تحديث العميل.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()->route('workspace.customers.index')->with('success', 'تم حذف العميل.');
    }
}
