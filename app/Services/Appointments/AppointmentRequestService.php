<?php

namespace App\Services\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentRequest;
use App\Models\Appointment\AppointmentRequestSlot;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Customer;
use App\Models\Workspace;
use App\Services\Notification\DomainNotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppointmentRequestService
{
    /** @var array<int, string> */
    public const REQUEST_STATUSES = ['new', 'reviewing', 'awaiting_customer', 'approved', 'rejected', 'expired', 'cancelled'];

    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentBillingService $appointmentBillingService,
        private readonly DomainNotificationService $domainNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createRequest(Workspace $workspace, array $payload, ?int $actorUserId = null, bool $aiGenerated = false): AppointmentRequest
    {
        $this->appointmentService->ensureSetup($workspace);
        $setting = AppointmentSetting::query()->first();
        $customer = $this->resolveCustomer($payload);

        $customerName = trim((string) ($payload['customer_name'] ?? ''));
        if ($customerName === '' && $customer) {
            $customerName = (string) $customer->name;
        }
        if ($customerName === '') {
            throw new RuntimeException('اسم العميل مطلوب لإنشاء طلب الموعد.');
        }

        $source = (string) ($payload['source'] ?? 'dashboard');
        if (! in_array($source, AppointmentService::BOOKING_SOURCES, true)) {
            $source = 'dashboard';
        }

        $automationMode = (string) ($payload['automation_mode'] ?? $setting?->automation_mode ?? 'APPROVAL');
        if (! in_array($automationMode, ['AUTO', 'APPROVAL', 'MANUAL'], true)) {
            $automationMode = 'APPROVAL';
        }
        $status = (string) ($payload['status'] ?? 'new');
        if (! in_array($status, self::REQUEST_STATUSES, true)) {
            $status = 'new';
        }

        $request = DB::transaction(function () use ($workspace, $payload, $customer, $customerName, $source, $automationMode, $aiGenerated, $status): AppointmentRequest {
            return AppointmentRequest::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'request_type' => (string) ($payload['request_type'] ?? 'new'),
                'target_booking_id' => isset($payload['target_booking_id']) ? (int) $payload['target_booking_id'] : null,
                'customer_id' => $customer?->id,
                'conversation_id' => isset($payload['conversation_id']) ? (int) $payload['conversation_id'] : null,
                'requested_service_id' => isset($payload['requested_service_id']) ? (int) $payload['requested_service_id'] : null,
                'requested_staff_id' => isset($payload['requested_staff_id']) ? (int) $payload['requested_staff_id'] : null,
                'customer_name' => $customerName,
                'customer_phone' => trim((string) ($payload['customer_phone'] ?? $customer?->phone ?? '')) ?: null,
                'customer_email' => trim((string) ($payload['customer_email'] ?? $customer?->email ?? '')) ?: null,
                'customer_age' => isset($payload['customer_age']) ? max(1, (int) $payload['customer_age']) : null,
                'requested_date' => ! empty($payload['requested_date']) ? Carbon::parse((string) $payload['requested_date'])->toDateString() : null,
                'requested_time' => trim((string) ($payload['requested_time'] ?? '')) ?: null,
                'requested_time_end' => trim((string) ($payload['requested_time_end'] ?? '')) ?: null,
                'status' => $status,
                'appointment_status' => null,
                'payment_status' => 'unpaid',
                'source' => $source,
                'automation_mode' => $automationMode,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'ai_generated' => $aiGenerated || (bool) ($payload['ai_generated'] ?? false),
                'ai_payload' => is_array($payload['ai_payload'] ?? null) ? $payload['ai_payload'] : null,
                'expires_at' => ! empty($payload['expires_at']) ? Carbon::parse((string) $payload['expires_at']) : null,
            ]);
        });

        $this->domainNotificationService->notifyAppointmentRequestCreated($request);

        if (
            $automationMode === 'AUTO'
            && ! empty($payload['requested_service_id'])
            && ! empty($payload['starts_at'])
        ) {
            $this->approveRequest($request, [
                'service_id' => (int) $payload['requested_service_id'],
                'staff_id' => isset($payload['requested_staff_id']) ? (int) $payload['requested_staff_id'] : null,
                'starts_at' => (string) $payload['starts_at'],
                'ends_at' => (string) ($payload['ends_at'] ?? ''),
                'source_channel' => $source,
                'notes' => trim((string) ($payload['notes'] ?? '')),
                'appointment_status' => 'scheduled',
            ], $actorUserId);
        }

        return $request->fresh(['service', 'staff', 'customer', 'slots']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function approveRequest(AppointmentRequest $request, array $payload, ?int $actorUserId): AppointmentBooking
    {
        if (in_array($request->status, ['approved', 'rejected', 'cancelled', 'expired'], true)) {
            throw new RuntimeException('لا يمكن اعتماد طلب موعد مغلق.');
        }

        $serviceId = (int) ($payload['service_id'] ?? $request->requested_service_id ?? 0);
        if ($serviceId <= 0) {
            throw new RuntimeException('الخدمة مطلوبة لاعتماد الطلب.');
        }

        $service = AppointmentServiceModel::query()->whereKey($serviceId)->firstOrFail();
        $staffId = isset($payload['staff_id'])
            ? (int) $payload['staff_id']
            : (isset($request->requested_staff_id) ? (int) $request->requested_staff_id : null);

        if ($staffId !== null && $staffId > 0) {
            AppointmentStaff::query()->whereKey($staffId)->firstOrFail();
        }

        [$startsAt, $endsAt] = $this->resolveBookingWindow($request, $service, $payload);
        $appointmentStatus = (string) ($payload['appointment_status'] ?? 'scheduled');
        if (! in_array($appointmentStatus, AppointmentService::APPOINTMENT_STATUSES, true)) {
            $appointmentStatus = 'scheduled';
        }

        $booking = $this->appointmentService->createBooking($request->workspace, [
            'request_id' => $request->id,
            'service_id' => $service->id,
            'staff_id' => $staffId,
            'customer_id' => $request->customer_id,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'customer_email' => $request->customer_email,
            'customer_age' => $request->customer_age,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'appointment_status' => $appointmentStatus,
            'status' => $appointmentStatus,
            'payment_status' => $service->requires_payment ? 'unpaid' : 'paid',
            'source_channel' => $payload['source_channel'] ?? $request->source,
            'notes' => $payload['notes'] ?? $request->notes,
            'resource_ids' => $payload['resource_ids'] ?? [],
            'metadata' => [
                'approved_from_request_id' => $request->id,
                'request_type' => $request->request_type,
            ],
        ], $actorUserId);

        $request->update([
            'requested_service_id' => $service->id,
            'requested_staff_id' => $staffId,
            'status' => 'approved',
            'appointment_status' => $booking->appointment_status,
            'payment_status' => $booking->payment_status,
            'approved_by' => $actorUserId,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'cancelled_at' => null,
        ]);

        if ($service->requires_payment && (float) $service->price > 0) {
            $booking = $this->appointmentBillingService->createInvoiceAndPaymentLink($booking, $actorUserId);
            $request->update(['payment_status' => $booking->payment_status]);
        }

        $this->domainNotificationService->notifyAppointmentBookingStatusChanged(
            $booking,
            'تم اعتماد طلب الموعد',
            'تمت الموافقة على طلب الموعد وإنشاء الحجز بنجاح.'
        );

        return $booking;
    }

    /**
     * @param  array<int, array<string,mixed>>  $slots
     * @return array<int, AppointmentRequestSlot>
     */
    public function proposeSlots(AppointmentRequest $request, array $slots, ?int $actorUserId): array
    {
        if ($slots === []) {
            throw new RuntimeException('يجب تقديم موعد واحد على الأقل.');
        }

        $created = [];

        DB::transaction(function () use ($request, $slots, $actorUserId, &$created): void {
            foreach ($slots as $slotPayload) {
                $startsAt = Carbon::parse((string) ($slotPayload['starts_at'] ?? ''));
                $endsAt = Carbon::parse((string) ($slotPayload['ends_at'] ?? ''));
                if ($endsAt->lte($startsAt)) {
                    throw new RuntimeException('نطاق وقت الموعد المقترح غير صحيح.');
                }

                $created[] = AppointmentRequestSlot::withoutGlobalScopes()->create([
                    'workspace_id' => $request->workspace_id,
                    'request_id' => $request->id,
                    'service_id' => isset($slotPayload['service_id']) ? (int) $slotPayload['service_id'] : $request->requested_service_id,
                    'staff_id' => isset($slotPayload['staff_id']) ? (int) $slotPayload['staff_id'] : $request->requested_staff_id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => 'proposed',
                    'proposed_by' => $actorUserId,
                    'expires_at' => ! empty($slotPayload['expires_at']) ? Carbon::parse((string) $slotPayload['expires_at']) : null,
                    'metadata' => is_array($slotPayload['metadata'] ?? null) ? $slotPayload['metadata'] : null,
                ]);
            }

            $request->update([
                'status' => 'awaiting_customer',
                'notes' => trim((string) ($request->notes ?? ''))."\nتم إرسال مواعيد مقترحة للعميل.",
            ]);
        });

        return $created;
    }

    public function selectSlot(AppointmentRequest $request, AppointmentRequestSlot $slot, ?int $actorUserId): AppointmentBooking
    {
        if ((int) $slot->request_id !== (int) $request->id) {
            throw new RuntimeException('الموعد المقترح لا ينتمي إلى نفس الطلب.');
        }

        DB::transaction(function () use ($request, $slot): void {
            AppointmentRequestSlot::query()
                ->where('request_id', $request->id)
                ->where('status', 'proposed')
                ->update(['status' => 'rejected']);

            $slot->update(['status' => 'selected']);
            $request->update([
                'status' => 'reviewing',
                'last_customer_response_at' => now(),
            ]);
        });

        return $this->approveRequest($request, [
            'slot_id' => $slot->id,
            'service_id' => $slot->service_id ?? $request->requested_service_id,
            'staff_id' => $slot->staff_id ?? $request->requested_staff_id,
            'starts_at' => $slot->starts_at?->toDateTimeString(),
            'ends_at' => $slot->ends_at?->toDateTimeString(),
            'appointment_status' => 'scheduled',
            'source_channel' => $request->source,
        ], $actorUserId);
    }

    public function rejectRequest(AppointmentRequest $request, ?int $actorUserId, ?string $reason = null): AppointmentRequest
    {
        $request->update([
            'status' => 'rejected',
            'rejected_by' => $actorUserId,
            'rejected_at' => now(),
            'notes' => trim((string) ($request->notes ?? '')).($reason ? "\nسبب الرفض: {$reason}" : ''),
        ]);

        return $request->refresh();
    }

    public function cancelRequest(AppointmentRequest $request, ?string $reason = null): AppointmentRequest
    {
        $request->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'notes' => trim((string) ($request->notes ?? '')).($reason ? "\nسبب الإلغاء: {$reason}" : ''),
        ]);

        return $request->refresh();
    }

    public function markAwaitingCustomer(AppointmentRequest $request, string $message): AppointmentRequest
    {
        $request->update([
            'status' => 'awaiting_customer',
            'notes' => trim((string) ($request->notes ?? ''))."\n".$message,
        ]);

        return $request->refresh();
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    public function listRequests(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $source = trim((string) ($filters['source'] ?? ''));
        $date = trim((string) ($filters['date'] ?? ''));

        return AppointmentRequest::query()
            ->with(['service', 'staff', 'customer', 'slots', 'booking'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%')
                        ->orWhere('customer_email', 'like', '%'.$search.'%')
                        ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($source !== '', fn ($query) => $query->where('source', $source))
            ->when($date !== '', fn ($query) => $query->whereDate('created_at', Carbon::parse($date)->toDateString()))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{0: Carbon,1: Carbon}
     */
    private function resolveBookingWindow(AppointmentRequest $request, AppointmentServiceModel $service, array $payload): array
    {
        if (! empty($payload['slot_id'])) {
            $slot = AppointmentRequestSlot::query()
                ->where('request_id', $request->id)
                ->whereKey((int) $payload['slot_id'])
                ->firstOrFail();

            return [
                Carbon::parse($slot->starts_at),
                Carbon::parse($slot->ends_at),
            ];
        }

        if (! empty($payload['starts_at'])) {
            $startsAt = Carbon::parse((string) $payload['starts_at']);
            if (! empty($payload['ends_at'])) {
                return [$startsAt, Carbon::parse((string) $payload['ends_at'])];
            }

            return [$startsAt, $startsAt->copy()->addMinutes((int) $service->duration_minutes)];
        }

        if ($request->requested_date && $request->requested_time) {
            $startsAt = Carbon::parse($request->requested_date->toDateString().' '.$request->requested_time);
            $endsAt = $request->requested_time_end
                ? Carbon::parse($request->requested_date->toDateString().' '.$request->requested_time_end)
                : $startsAt->copy()->addMinutes((int) $service->duration_minutes);

            return [$startsAt, $endsAt];
        }

        throw new RuntimeException('وقت الموعد غير مكتمل لاعتماد الطلب.');
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function resolveCustomer(array $payload): ?Customer
    {
        if (! empty($payload['customer_id'])) {
            return Customer::query()->whereKey((int) $payload['customer_id'])->first();
        }

        $phone = trim((string) ($payload['customer_phone'] ?? ''));
        if ($phone !== '') {
            $byPhone = Customer::query()->where('phone', $phone)->first();
            if ($byPhone) {
                return $byPhone;
            }
        }

        $email = trim((string) ($payload['customer_email'] ?? ''));
        if ($email !== '') {
            return Customer::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();
        }

        return null;
    }
}
