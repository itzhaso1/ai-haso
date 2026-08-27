<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Customer;
use App\Models\WorkspaceUser;
use App\Services\Appointments\AppointmentService;
use App\Services\Appointments\AppointmentRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends AppointmentsBaseController
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentRequestService $appointmentRequestService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAppointments($request, 'appointments.view');
        $workspace = $this->currentWorkspace();
        $this->appointmentService->ensureSetup($workspace);

        $filters = [
            'date' => trim((string) $request->string('date', now()->toDateString())),
            'status' => trim((string) $request->string('status')),
            'payment_status' => trim((string) $request->string('payment_status')),
            'staff_id' => $request->integer('staff_id') ?: null,
            'search' => trim((string) $request->string('search')),
        ];
        $requestFilters = [
            'date' => trim((string) $request->string('request_date')),
            'status' => trim((string) $request->string('request_status')),
            'source' => trim((string) $request->string('request_source')),
            'search' => trim((string) $request->string('request_search')),
        ];

        $bookings = $this->appointmentService->listBookings($filters, 20);
        $appointmentRequests = $this->appointmentRequestService->listRequests($requestFilters, 15);

        $today = now()->toDateString();
        $todayStats = [
            'scheduled' => AppointmentBooking::query()->whereDate('starts_at', $today)->where('appointment_status', 'scheduled')->count(),
            'confirmed' => AppointmentBooking::query()->whereDate('starts_at', $today)->where('appointment_status', 'confirmed')->count(),
            'completed' => AppointmentBooking::query()->whereDate('starts_at', $today)->where('appointment_status', 'completed')->count(),
            'cancelled' => AppointmentBooking::query()->whereDate('starts_at', $today)->where('appointment_status', 'cancelled')->count(),
        ];

        return view('workspace.appointments.index', [
            'setting' => AppointmentSetting::query()->first(),
            'services' => AppointmentServiceModel::query()
                ->with('staffMembers:id,name')
                ->latest('id')
                ->paginate(10, ['*'], 'services_page'),
            'staff' => AppointmentStaff::query()
                ->with(['user', 'services:id,name'])
                ->latest('id')
                ->paginate(10, ['*'], 'staff_page'),
            'allServices' => AppointmentServiceModel::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'duration_minutes']),
            'allStaff' => AppointmentStaff::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'bookings' => $bookings,
            'appointmentRequests' => $appointmentRequests,
            'customers' => Customer::query()->orderBy('name')->limit(200)->get(['id', 'name', 'phone']),
            'workspaceUsers' => WorkspaceUser::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->with('user')
                ->orderBy('membership_role')
                ->get(),
            'filters' => $filters,
            'requestFilters' => $requestFilters,
            'todayStats' => $todayStats,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $workspace = $this->currentWorkspace();

        $validated = $request->validate([
            'business_type' => ['required', Rule::in(['pharmacy', 'clinic', 'hospital', 'salon', 'general', 'other'])],
            'business_label' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:64'],
            'slot_interval_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'start_hour' => ['required', 'date_format:H:i'],
            'end_hour' => ['required', 'date_format:H:i', 'after:start_hour'],
            'allow_walk_in' => ['nullable', 'boolean'],
            'automation_mode' => ['required', Rule::in(['AUTO', 'APPROVAL', 'MANUAL'])],
            'auto_confirm_after_payment' => ['nullable', 'boolean'],
            'reminder_offsets' => ['nullable', 'string', 'max:255'],
        ]);

        $reminderOffsets = collect(explode(',', (string) ($validated['reminder_offsets'] ?? '1440,120')))
            ->map(fn (string $value): int => max(1, (int) trim($value)))
            ->filter(fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        $this->appointmentService->updateSetting($workspace, $validated + [
            'allow_walk_in' => $request->boolean('allow_walk_in'),
            'auto_confirm_after_payment' => $request->boolean('auto_confirm_after_payment', true),
            'reminder_offsets' => $reminderOffsets === [] ? [1440, 120] : $reminderOffsets,
        ]);

        return back()->with('success', 'تم تحديث إعدادات المواعيد.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $workspace = $this->currentWorkspace();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'price' => ['required', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'requires_confirmation' => ['nullable', 'boolean'],
            'requires_payment' => ['nullable', 'boolean'],
            'payment_mode' => ['nullable', Rule::in(['full', 'deposit', 'postpaid'])],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'approval_required' => ['nullable', 'boolean'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer'],
        ]);

        $this->appointmentService->createService($workspace, $validated + [
            'is_active' => $request->boolean('is_active', true),
            'requires_confirmation' => $request->boolean('requires_confirmation'),
            'requires_payment' => $request->boolean('requires_payment'),
            'approval_required' => $request->boolean('approval_required'),
        ]);

        return back()->with('success', 'تمت إضافة خدمة الموعد.');
    }

    public function updateService(Request $request, AppointmentServiceModel $service): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'price' => ['required', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'requires_confirmation' => ['nullable', 'boolean'],
            'requires_payment' => ['nullable', 'boolean'],
            'payment_mode' => ['nullable', Rule::in(['full', 'deposit', 'postpaid'])],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'approval_required' => ['nullable', 'boolean'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer'],
        ]);

        $this->appointmentService->updateService($service, $validated + [
            'is_active' => $request->boolean('is_active', true),
            'requires_confirmation' => $request->boolean('requires_confirmation'),
            'requires_payment' => $request->boolean('requires_payment'),
            'approval_required' => $request->boolean('approval_required'),
        ]);

        return back()->with('success', 'تم تحديث الخدمة.');
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'working_days' => ['nullable', 'array'],
            'working_days.*' => ['string', Rule::in(['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'])],
            'working_hours' => ['nullable', 'array'],
            'vacation_periods' => ['nullable', 'array'],
            'staff_permissions' => ['nullable', 'array'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer'],
        ]);

        $this->appointmentService->createStaff($workspace, $validated + [
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'تمت إضافة عضو الطاقم.');
    }

    public function updateStaff(Request $request, AppointmentStaff $staff): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'working_days' => ['nullable', 'array'],
            'working_days.*' => ['string', Rule::in(['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'])],
            'working_hours' => ['nullable', 'array'],
            'vacation_periods' => ['nullable', 'array'],
            'staff_permissions' => ['nullable', 'array'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer'],
        ]);

        $this->appointmentService->updateStaff($staff, $validated + [
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'تم تحديث بيانات الطاقم.');
    }

    public function storeBooking(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'])],
            'payment_status' => ['nullable', Rule::in(['unpaid', 'pending', 'paid', 'failed', 'refunded', 'partially_paid'])],
            'source' => ['nullable', Rule::in(['dashboard', 'phone', 'walk_in', 'website', 'whatsapp', 'ai_chat', 'email', 'api'])],
            'notes' => ['nullable', 'string'],
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => ['integer'],
        ]);

        try {
            $this->appointmentService->createBooking($workspace, [
                ...$validated,
                'source_channel' => $validated['source'] ?? 'dashboard',
                'appointment_status' => $validated['status'] ?? 'scheduled',
            ], (int) $request->user()?->id);
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إنشاء الموعد بنجاح.');
    }

    public function updateBookingStatus(Request $request, AppointmentBooking $booking): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $validated = $request->validate([
            'status' => ['required', Rule::in(['scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'])],
            'cancel_reason' => ['nullable', 'string'],
        ]);

        $this->appointmentService->updateBookingStatus($booking, [
            'appointment_status' => $validated['status'],
            'cancel_reason' => $validated['cancel_reason'] ?? null,
        ]);

        return back()->with('success', 'تم تحديث حالة الموعد.');
    }
}
