<?php

namespace App\Http\Controllers\Workspace\Finance;

use App\Models\Finance\FinanceEmployeeProfile;
use App\Models\WorkspaceUser;
use App\Services\Finance\FinanceBootstrapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceEmployeeController extends FinanceBaseController
{
    public function __construct(
        private readonly FinanceBootstrapService $financeBootstrapService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeFinance($request, 'payroll.view');
        $workspace = $this->currentWorkspace();
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $profiles = FinanceEmployeeProfile::query()
            ->with('user')
            ->latest('id')
            ->paginate(15);

        $workspaceEmployees = WorkspaceUser::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->with('user')
            ->orderBy('membership_role')
            ->get();

        return view('workspace.finance.modules.employees', [
            'profiles' => $profiles,
            'workspaceEmployees' => $workspaceEmployees,
            'roles' => [
                'accountant' => 'محاسب',
                'invoicing_officer' => 'موظف فواتير',
                'cashier' => 'أمين صندوق',
                'collector' => 'محصل',
                'auditor' => 'مدقق',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeFinance($request, 'payroll.manage');
        $workspace = $this->currentWorkspace();
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $validated = $request->validate($this->rules($workspace->id));

        $profile = FinanceEmployeeProfile::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($profile) {
            $profile->update($this->payload($validated));
        } else {
            FinanceEmployeeProfile::query()->create(array_merge(
                ['workspace_id' => $workspace->id],
                $this->payload($validated)
            ));
        }

        return back()->with('success', 'تم حفظ ملف موظف المالية بنجاح.');
    }

    public function update(Request $request, FinanceEmployeeProfile $employee): RedirectResponse
    {
        $this->authorizeFinance($request, 'payroll.manage');
        $workspace = $this->currentWorkspace();
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);
        abort_unless((int) $employee->workspace_id === (int) $workspace->id, 404);

        $validated = $request->validate($this->rules($workspace->id, (int) $employee->id));
        $employee->update($this->payload($validated));

        return back()->with('success', 'تم تحديث موظف المالية.');
    }

    /**
     * @return array<string,mixed>
     */
    private function rules(int $workspaceId, ?int $ignoreProfileId = null): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('workspace_users', 'user_id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)->where('status', 'active')
                ),
                Rule::unique('finance_employee_profiles', 'user_id')
                    ->where(fn ($query) => $query->where('workspace_id', $workspaceId))
                    ->ignore($ignoreProfileId),
            ],
            'finance_role' => ['nullable', 'string', 'max:120'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'numeric', 'min:0'],
            'default_deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array<string,mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'user_id' => (int) $validated['user_id'],
            'finance_role' => $validated['finance_role'] ?? null,
            'basic_salary' => (float) ($validated['basic_salary'] ?? 0),
            'housing_allowance' => (float) ($validated['housing_allowance'] ?? 0),
            'transport_allowance' => (float) ($validated['transport_allowance'] ?? 0),
            'other_allowances' => (float) ($validated['other_allowances'] ?? 0),
            'default_deductions' => (float) ($validated['default_deductions'] ?? 0),
            'notes' => $validated['notes'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }
}
