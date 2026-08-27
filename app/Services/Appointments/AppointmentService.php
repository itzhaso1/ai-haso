<?php

namespace App\Services\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentResource;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Customer;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AppointmentService
{
    /** @var array<int, string> */
    public const APPOINTMENT_STATUSES = ['scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'];
    /** @var array<int, string> */
    public const PAYMENT_STATUSES = ['unpaid', 'pending', 'paid', 'failed', 'refunded', 'partially_paid'];
    /** @var array<int, string> */
    public const BOOKING_SOURCES = ['dashboard', 'phone', 'walk_in', 'website', 'whatsapp', 'ai_chat', 'email', 'api'];

    public function ensureSetup(Workspace $workspace): void
    {
        AppointmentSetting::withoutGlobalScopes()->firstOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'workspace_id' => $workspace->id,
                'business_type' => 'general',
                'business_label' => $workspace->name,
                'timezone' => 'Asia/Riyadh',
                'slot_interval_minutes' => 30,
                'start_hour' => '08:00:00',
                'end_hour' => '22:00:00',
                'allow_walk_in' => true,
                'automation_mode' => 'APPROVAL',
                'auto_confirm_after_payment' => true,
                'reminder_offsets' => [1440, 120],
            ]
        );
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateSetting(Workspace $workspace, array $payload): AppointmentSetting
    {
        $this->ensureSetup($workspace);
        $setting = AppointmentSetting::query()->firstOrFail();
        $setting->update([
            'business_type' => $payload['business_type'],
            'business_label' => trim((string) ($payload['business_label'] ?? '')) ?: null,
            'timezone' => $payload['timezone'],
            'slot_interval_minutes' => (int) $payload['slot_interval_minutes'],
            'start_hour' => $payload['start_hour'],
            'end_hour' => $payload['end_hour'],
            'allow_walk_in' => (bool) ($payload['allow_walk_in'] ?? false),
            'automation_mode' => (string) ($payload['automation_mode'] ?? $setting->automation_mode ?? 'APPROVAL'),
            'auto_confirm_after_payment' => (bool) ($payload['auto_confirm_after_payment'] ?? true),
            'reminder_offsets' => $payload['reminder_offsets'] ?? [1440, 120],
        ]);

        return $setting->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createService(Workspace $workspace, array $payload): AppointmentServiceModel
    {
        $service = AppointmentServiceModel::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => trim((string) $payload['name']),
            'description' => trim((string) ($payload['description'] ?? '')) ?: null,
            'duration_minutes' => (int) $payload['duration_minutes'],
            'price' => round((float) $payload['price'], 2),
            'color' => trim((string) ($payload['color'] ?? '')) ?: null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
            'requires_payment' => (bool) ($payload['requires_payment'] ?? false),
            'payment_mode' => (string) ($payload['payment_mode'] ?? 'postpaid'),
            'deposit_amount' => isset($payload['deposit_amount']) ? round((float) $payload['deposit_amount'], 2) : null,
            'approval_required' => (bool) ($payload['approval_required'] ?? false),
        ]);

        $staffIds = collect($payload['staff_ids'] ?? [])->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->all();
        if ($staffIds !== []) {
            $service->staffMembers()->sync(collect($staffIds)->mapWithKeys(
                fn (int $id): array => [$id => ['workspace_id' => $workspace->id, 'is_primary' => false]]
            )->all());
        }

        return $service->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateService(AppointmentServiceModel $service, array $payload): AppointmentServiceModel
    {
        $service->update([
            'name' => trim((string) $payload['name']),
            'description' => trim((string) ($payload['description'] ?? '')) ?: null,
            'duration_minutes' => (int) $payload['duration_minutes'],
            'price' => round((float) $payload['price'], 2),
            'color' => trim((string) ($payload['color'] ?? '')) ?: null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
            'requires_payment' => (bool) ($payload['requires_payment'] ?? $service->requires_payment),
            'payment_mode' => (string) ($payload['payment_mode'] ?? $service->payment_mode ?? 'postpaid'),
            'deposit_amount' => isset($payload['deposit_amount'])
                ? round((float) $payload['deposit_amount'], 2)
                : $service->deposit_amount,
            'approval_required' => (bool) ($payload['approval_required'] ?? $service->approval_required),
        ]);

        if (is_array($payload['staff_ids'] ?? null)) {
            $staffIds = collect($payload['staff_ids'])->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->all();
            $service->staffMembers()->sync(collect($staffIds)->mapWithKeys(
                fn (int $id): array => [$id => ['workspace_id' => $service->workspace_id, 'is_primary' => false]]
            )->all());
        }

        return $service->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createStaff(Workspace $workspace, array $payload): AppointmentStaff
    {
        $staff = AppointmentStaff::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $payload['user_id'] ?? null,
            'name' => trim((string) $payload['name']),
            'role' => trim((string) ($payload['role'] ?? '')) ?: null,
            'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
            'color' => trim((string) ($payload['color'] ?? '')) ?: null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'working_days' => is_array($payload['working_days'] ?? null) ? $payload['working_days'] : null,
            'working_hours' => is_array($payload['working_hours'] ?? null) ? $payload['working_hours'] : null,
            'vacation_periods' => is_array($payload['vacation_periods'] ?? null) ? $payload['vacation_periods'] : null,
            'staff_permissions' => is_array($payload['staff_permissions'] ?? null) ? $payload['staff_permissions'] : null,
        ]);

        if (is_array($payload['service_ids'] ?? null)) {
            $serviceIds = collect($payload['service_ids'])->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->all();
            $staff->services()->sync(collect($serviceIds)->mapWithKeys(
                fn (int $id): array => [$id => ['workspace_id' => $workspace->id, 'is_primary' => false]]
            )->all());
        }

        return $staff->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateStaff(AppointmentStaff $staff, array $payload): AppointmentStaff
    {
        $staff->update([
            'user_id' => $payload['user_id'] ?? null,
            'name' => trim((string) $payload['name']),
            'role' => trim((string) ($payload['role'] ?? '')) ?: null,
            'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
            'color' => trim((string) ($payload['color'] ?? '')) ?: null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'working_days' => is_array($payload['working_days'] ?? null) ? $payload['working_days'] : $staff->working_days,
            'working_hours' => is_array($payload['working_hours'] ?? null) ? $payload['working_hours'] : $staff->working_hours,
            'vacation_periods' => is_array($payload['vacation_periods'] ?? null) ? $payload['vacation_periods'] : $staff->vacation_periods,
            'staff_permissions' => is_array($payload['staff_permissions'] ?? null) ? $payload['staff_permissions'] : $staff->staff_permissions,
        ]);

        if (is_array($payload['service_ids'] ?? null)) {
            $serviceIds = collect($payload['service_ids'])->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->all();
            $staff->services()->sync(collect($serviceIds)->mapWithKeys(
                fn (int $id): array => [$id => ['workspace_id' => $staff->workspace_id, 'is_primary' => false]]
            )->all());
        }

        return $staff->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createBooking(Workspace $workspace, array $payload, ?int $actorUserId): AppointmentBooking
    {
        $service = AppointmentServiceModel::query()->whereKey((int) $payload['service_id'])->firstOrFail();
        $staff = null;
        if (! empty($payload['staff_id'])) {
            $staff = AppointmentStaff::query()->whereKey((int) $payload['staff_id'])->firstOrFail();
        }

        $startsAt = Carbon::parse((string) $payload['starts_at']);
        $endsAt = Carbon::parse((string) $payload['ends_at']);
        if ($endsAt->lte($startsAt)) {
            throw new RuntimeException('وقت نهاية الموعد يجب أن يكون بعد وقت البداية.');
        }

        $resourceIds = collect($payload['resource_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $this->ensureNoOverlap($workspace->id, $startsAt, $endsAt, $staff?->id, $resourceIds);

        $customerName = trim((string) ($payload['customer_name'] ?? ''));
        $customerPhone = trim((string) ($payload['customer_phone'] ?? '')) ?: null;
        $customerEmail = trim((string) ($payload['customer_email'] ?? '')) ?: null;
        $customerAge = isset($payload['customer_age']) ? max(1, (int) $payload['customer_age']) : null;
        $customerId = null;
        if (! empty($payload['customer_id'])) {
            $customer = Customer::query()->whereKey((int) $payload['customer_id'])->firstOrFail();
            $customerId = $customer->id;
            if ($customerName === '') {
                $customerName = $customer->name;
            }
            if ($customerPhone === null && filled($customer->phone)) {
                $customerPhone = $customer->phone;
            }
            if ($customerEmail === null && filled($customer->email)) {
                $customerEmail = $customer->email;
            }
        }

        if ($customerName === '') {
            throw new RuntimeException('اسم العميل مطلوب لإنشاء الحجز.');
        }

        $sourceChannel = trim((string) ($payload['source_channel'] ?? $payload['source'] ?? 'dashboard'));
        if (! in_array($sourceChannel, self::BOOKING_SOURCES, true)) {
            $sourceChannel = 'dashboard';
        }

        $legacyStatus = (string) ($payload['status'] ?? $payload['appointment_status'] ?? 'scheduled');
        if (! in_array($legacyStatus, ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'], true)) {
            $legacyStatus = 'scheduled';
        }
        $appointmentStatus = (string) ($payload['appointment_status'] ?? $legacyStatus);
        if (! in_array($appointmentStatus, self::APPOINTMENT_STATUSES, true)) {
            $appointmentStatus = 'scheduled';
        }
        $paymentStatus = (string) ($payload['payment_status'] ?? ($service->requires_payment ? 'unpaid' : 'paid'));
        if (! in_array($paymentStatus, self::PAYMENT_STATUSES, true)) {
            $paymentStatus = $service->requires_payment ? 'unpaid' : 'paid';
        }

        return DB::transaction(function () use (
            $workspace,
            $service,
            $staff,
            $payload,
            $actorUserId,
            $startsAt,
            $endsAt,
            $customerId,
            $customerName,
            $customerPhone,
            $customerEmail,
            $customerAge,
            $sourceChannel,
            $legacyStatus,
            $appointmentStatus,
            $paymentStatus,
            $resourceIds
        ): AppointmentBooking {
            $bookingNumber = $this->nextBookingNumber($workspace->id, $startsAt);

            $booking = AppointmentBooking::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'booking_number' => $bookingNumber,
                'request_id' => isset($payload['request_id']) ? (int) $payload['request_id'] : null,
                'service_id' => $service->id,
                'staff_id' => $staff?->id,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
                'customer_age' => $customerAge,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $legacyStatus,
                'source' => $this->resolveLegacySource($sourceChannel),
                'source_channel' => $sourceChannel,
                'appointment_status' => $appointmentStatus,
                'payment_status' => $paymentStatus,
                'finance_invoice_id' => isset($payload['finance_invoice_id']) ? (int) $payload['finance_invoice_id'] : null,
                'order_id' => isset($payload['order_id']) ? (int) $payload['order_id'] : null,
                'latest_payment_id' => isset($payload['latest_payment_id']) ? (int) $payload['latest_payment_id'] : null,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'public_token' => Str::lower((string) Str::ulid()),
                'payment_link' => trim((string) ($payload['payment_link'] ?? '')) ?: null,
                'confirmed_at' => $appointmentStatus === 'confirmed' ? now() : null,
                'checked_in_at' => $appointmentStatus === 'checked_in' ? now() : null,
                'in_progress_at' => $appointmentStatus === 'in_progress' ? now() : null,
                'completed_at' => $appointmentStatus === 'completed' ? now() : null,
                'cancelled_at' => $appointmentStatus === 'cancelled' ? now() : null,
                'booked_by' => $actorUserId,
                'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null,
            ]);

            if ($resourceIds !== []) {
                $resources = AppointmentResource::query()
                    ->whereIn('id', $resourceIds)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->all();
                if ($resources !== []) {
                    $booking->resources()->sync(collect($resources)->mapWithKeys(
                        fn (int $id): array => [$id => ['workspace_id' => $workspace->id]]
                    )->all());
                }
            }

            return $booking->fresh(['service', 'staff', 'customer', 'resources']);
        });
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateBookingStatus(AppointmentBooking $booking, array $payload): AppointmentBooking
    {
        $requestedStatus = (string) ($payload['status'] ?? $payload['appointment_status'] ?? '');
        if (! in_array($requestedStatus, self::APPOINTMENT_STATUSES, true)) {
            throw new RuntimeException('حالة الموعد غير صالحة.');
        }

        $legacyStatus = in_array($requestedStatus, ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'], true)
            ? $requestedStatus
            : ($requestedStatus === 'checked_in' || $requestedStatus === 'in_progress' ? 'confirmed' : 'scheduled');

        $booking->update([
            'status' => $legacyStatus,
            'appointment_status' => $requestedStatus,
            'cancel_reason' => trim((string) ($payload['cancel_reason'] ?? '')) ?: null,
            'confirmed_at' => $requestedStatus === 'confirmed' ? now() : $booking->confirmed_at,
            'checked_in_at' => $requestedStatus === 'checked_in' ? now() : $booking->checked_in_at,
            'in_progress_at' => $requestedStatus === 'in_progress' ? now() : $booking->in_progress_at,
            'completed_at' => $requestedStatus === 'completed' ? now() : $booking->completed_at,
            'cancelled_at' => $requestedStatus === 'cancelled' ? now() : $booking->cancelled_at,
        ]);

        return $booking->refresh();
    }

    public function cancelBooking(AppointmentBooking $booking, ?string $reason = null): AppointmentBooking
    {
        $booking->update([
            'status' => 'cancelled',
            'appointment_status' => 'cancelled',
            'cancel_reason' => trim((string) $reason) ?: null,
            'cancelled_at' => now(),
        ]);

        return $booking->refresh();
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    public function listBookings(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $date = trim((string) ($filters['date'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $paymentStatus = trim((string) ($filters['payment_status'] ?? ''));
        $staffId = (int) ($filters['staff_id'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        return AppointmentBooking::query()
            ->with(['service', 'staff', 'customer', 'booker', 'request', 'invoice'])
            ->when($date !== '', fn ($query) => $query->whereDate('starts_at', Carbon::parse($date)->toDateString()))
            ->when($status !== '', fn ($query) => $query->where('appointment_status', $status))
            ->when($paymentStatus !== '', fn ($query) => $query->where('payment_status', $paymentStatus))
            ->when($staffId > 0, fn ($query) => $query->where('staff_id', $staffId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('booking_number', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%')
                        ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('starts_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<int, int>  $resourceIds
     */
    private function ensureNoOverlap(int $workspaceId, Carbon $startsAt, Carbon $endsAt, ?int $staffId = null, array $resourceIds = []): void
    {
        $baseQuery = AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereIn('appointment_status', ['scheduled', 'confirmed', 'checked_in', 'in_progress'])
            ->where(function ($query) use ($startsAt, $endsAt): void {
                $query->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
            });

        $staffOverlap = $staffId !== null
            ? (clone $baseQuery)->where('staff_id', $staffId)->exists()
            : false;

        $resourceOverlap = $resourceIds !== []
            ? (clone $baseQuery)->whereHas('resources', fn ($query) => $query->whereIn('appointment_resources.id', $resourceIds))->exists()
            : false;

        if ($staffOverlap) {
            throw new RuntimeException('يوجد تعارض: هذا الوقت محجوز بالفعل لنفس الطاقم.');
        }

        if ($resourceOverlap) {
            throw new RuntimeException('يوجد تعارض: أحد الموارد المطلوبة محجوز في نفس التوقيت.');
        }
    }

    private function nextBookingNumber(int $workspaceId, Carbon $date): string
    {
        $prefix = 'APT-'.$date->format('Ymd').'-';
        $last = AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('booking_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        if (! $last) {
            return $prefix.'0001';
        }

        $seq = (int) substr($last->booking_number, -4);

        return $prefix.str_pad((string) ($seq + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function calendarEvents(array $filters): array
    {
        $view = (string) ($filters['view'] ?? 'week');
        $date = isset($filters['date']) ? Carbon::parse((string) $filters['date']) : now();
        $rangeStart = $view === 'month'
            ? $date->copy()->startOfMonth()->startOfDay()
            : ($view === 'day' ? $date->copy()->startOfDay() : $date->copy()->startOfWeek()->startOfDay());
        $rangeEnd = $view === 'month'
            ? $date->copy()->endOfMonth()->endOfDay()
            : ($view === 'day' ? $date->copy()->endOfDay() : $date->copy()->endOfWeek()->endOfDay());

        return AppointmentBooking::query()
            ->with(['service:id,name', 'staff:id,name'])
            ->where('starts_at', '>=', $rangeStart)
            ->where('starts_at', '<=', $rangeEnd)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (AppointmentBooking $booking): array => [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'title' => sprintf('%s - %s', (string) $booking->customer_name, (string) ($booking->service?->name ?? 'خدمة')),
                'customer' => $booking->customer_name,
                'service' => $booking->service?->name,
                'staff' => $booking->staff?->name,
                'start' => $booking->starts_at?->toIso8601String(),
                'end' => $booking->ends_at?->toIso8601String(),
                'appointment_status' => $booking->appointment_status,
                'payment_status' => $booking->payment_status,
            ])
            ->values()
            ->all();
    }

    private function resolveLegacySource(string $sourceChannel): string
    {
        return match ($sourceChannel) {
            'ai_chat', 'email', 'api' => 'dashboard',
            default => $sourceChannel,
        };
    }
}
