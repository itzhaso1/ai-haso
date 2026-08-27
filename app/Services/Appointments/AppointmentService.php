<?php

namespace App\Services\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Customer;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppointmentService
{
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
        ]);

        return $setting->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createService(Workspace $workspace, array $payload): AppointmentServiceModel
    {
        return AppointmentServiceModel::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => trim((string) $payload['name']),
            'description' => trim((string) ($payload['description'] ?? '')) ?: null,
            'duration_minutes' => (int) $payload['duration_minutes'],
            'price' => round((float) $payload['price'], 2),
            'color' => trim((string) ($payload['color'] ?? '')) ?: null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
        ]);
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
        ]);

        return $service->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createStaff(Workspace $workspace, array $payload): AppointmentStaff
    {
        return AppointmentStaff::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $payload['user_id'] ?? null,
            'name' => trim((string) $payload['name']),
            'role' => trim((string) ($payload['role'] ?? '')) ?: null,
            'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
            'color' => trim((string) ($payload['color'] ?? '')) ?: null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ]);
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
        ]);

        return $staff->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createBooking(Workspace $workspace, array $payload, int $actorUserId): AppointmentBooking
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

        $this->ensureNoOverlap($workspace->id, $startsAt, $endsAt, $staff?->id);

        $customerName = trim((string) ($payload['customer_name'] ?? ''));
        $customerPhone = trim((string) ($payload['customer_phone'] ?? '')) ?: null;
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
        }

        if ($customerName === '') {
            throw new RuntimeException('اسم العميل مطلوب لإنشاء الحجز.');
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
            $customerPhone
        ): AppointmentBooking {
            $bookingNumber = $this->nextBookingNumber($workspace->id, $startsAt);

            return AppointmentBooking::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'booking_number' => $bookingNumber,
                'service_id' => $service->id,
                'staff_id' => $staff?->id,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $payload['status'] ?? 'scheduled',
                'source' => $payload['source'] ?? 'dashboard',
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'booked_by' => $actorUserId,
            ]);
        });
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateBookingStatus(AppointmentBooking $booking, array $payload): AppointmentBooking
    {
        $booking->update([
            'status' => $payload['status'],
            'cancel_reason' => trim((string) ($payload['cancel_reason'] ?? '')) ?: null,
        ]);

        return $booking->refresh();
    }

    public function cancelBooking(AppointmentBooking $booking, ?string $reason = null): AppointmentBooking
    {
        $booking->update([
            'status' => 'cancelled',
            'cancel_reason' => trim((string) $reason) ?: null,
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
        $staffId = (int) ($filters['staff_id'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        return AppointmentBooking::query()
            ->with(['service', 'staff', 'customer', 'booker'])
            ->when($date !== '', fn ($query) => $query->whereDate('starts_at', Carbon::parse($date)->toDateString()))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
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

    private function ensureNoOverlap(int $workspaceId, Carbon $startsAt, Carbon $endsAt, ?int $staffId = null): void
    {
        $exists = AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->when($staffId !== null, fn ($query) => $query->where('staff_id', $staffId))
            ->where(function ($query) use ($startsAt, $endsAt): void {
                $query->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
            })
            ->exists();

        if ($exists) {
            throw new RuntimeException('يوجد تعارض: هذا الوقت محجوز بالفعل لنفس الطاقم.');
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
}
