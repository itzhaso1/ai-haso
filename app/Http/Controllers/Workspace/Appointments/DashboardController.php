<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Customer;
use App\Models\WorkspaceUser;
use App\Services\Appointments\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends AppointmentsBaseController
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAppointments($request, 'appointments.view');
        $workspace = $this->currentWorkspace();
        $this->appointmentService->ensureSetup($workspace);

        $filters = [
            'date' => trim((string) $request->string('date', now()->toDateString())),
            'status' => trim((string) $request->string('status')),
            'staff_id' => $request->integer('staff_id') ?: null,
            'search' => trim((string) $request->string('search')),
        ];

        $bookings = $this->appointmentService->listBookings($filters, 20);

        $today = now()->toDateString();
        $todayStats = [
            'scheduled' => AppointmentBooking::query()->whereDate('starts_at', $today)->where('status', 'scheduled')->count(),
            'confirmed' => AppointmentBooking::query()->whereDate('starts_at', $today)->where('status', 'confirmed')->count(),
            'completed' => AppointmentBooking::query()->whereDate('starts_at', $today)->where('status', 'completed')->count(),
            'cancelled' => AppointmentBooking::query()->whereDate('starts_at', $today)->where('status', 'cancelled')->count(),
        ];

        return view('workspace.appointments.index', [
            'setting' => AppointmentSetting::query()->first(),
            'services' => AppointmentServiceModel::query()->latest('id')->paginate(10, ['*'], 'services_page'),
            'staff' => AppointmentStaff::query()->with('user')->latest('id')->paginate(10, ['*'], 'staff_page'),
            'allServices' => AppointmentServiceModel::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'duration_minutes']),
            'allStaff' => AppointmentStaff::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'bookings' => $bookings,
            'customers' => Customer::query()->orderBy('name')->limit(200)->get(['id', 'name', 'phone']),
            'workspaceUsers' => WorkspaceUser::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->with('user')
                ->orderBy('membership_role')
                ->get(),
            'filters' => $filters,
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
        ]);

        $this->appointmentService->updateSetting($workspace, $validated + [
            'allow_walk_in' => $request->boolean('allow_walk_in'),
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
        ]);

        $this->appointmentService->createService($workspace, $validated + [
            'is_active' => $request->boolean('is_active', true),
            'requires_confirmation' => $request->boolean('requires_confirmation'),
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
        ]);

        $this->appointmentService->updateService($service, $validated + [
            'is_active' => $request->boolean('is_active', true),
            'requires_confirmation' => $request->boolean('requires_confirmation'),
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
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'])],
            'source' => ['nullable', Rule::in(['dashboard', 'phone', 'walk_in', 'website', 'whatsapp'])],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $this->appointmentService->createBooking($workspace, $validated, (int) $request->user()?->id);
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إنشاء الموعد بنجاح.');
    }

    public function updateBookingStatus(Request $request, AppointmentBooking $booking): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $validated = $request->validate([
            'status' => ['required', Rule::in(['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'])],
            'cancel_reason' => ['nullable', 'string'],
        ]);

        $this->appointmentService->updateBookingStatus($booking, $validated);

        return back()->with('success', 'تم تحديث حالة الموعد.');
    }
}
