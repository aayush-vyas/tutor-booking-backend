<?php

namespace App\Services;

use App\Dto\Availability\CreateAvailabilityDto;
use App\Models\TutorAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AvailabilityService
{
    /**
     * Create a new availability slot.
     *
     * @throws InvalidArgumentException
     */
    public function createAvailability(CreateAvailabilityDto $dto): TutorAvailability
    {
        $this->validateTimeRange($dto->startTime, $dto->endTime);
        $this->validateNoOverlappingSlots($dto->tutorId, $dto->startTime, $dto->endTime);

        return DB::transaction(function () use ($dto) {
            return TutorAvailability::create($dto->toArray());
        });
    }

    /**
     * Update an availability slot.
     *
     * @throws InvalidArgumentException
     */
    public function updateAvailability(
        TutorAvailability $availability,
        Carbon $startTime,
        Carbon $endTime,
        bool $isAvailable,
        ?string $notes = null
    ): TutorAvailability {
        $this->validateTimeRange($startTime, $endTime);
        $this->validateNoOverlappingSlots(
            $availability->tutor_id,
            $startTime,
            $endTime,
            $availability->id
        );

        return DB::transaction(function () use ($availability, $startTime, $endTime, $isAvailable, $notes) {
            $availability->update([
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_available' => $isAvailable,
                'notes' => $notes,
            ]);

            return $availability->fresh();
        });
    }

    /**
     * Delete an availability slot.
     *
     * @throws InvalidArgumentException
     */
    public function deleteAvailability(TutorAvailability $availability): bool
    {
        $hasBookings = DB::table('bookings')
            ->where('tutor_id', $availability->tutor_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) use ($availability) {
                $query->whereBetween('start_time', [$availability->start_time, $availability->end_time])
                    ->orWhereBetween('end_time', [$availability->start_time, $availability->end_time])
                    ->orWhere(function ($q) use ($availability) {
                        $q->where('start_time', '<=', $availability->start_time)
                            ->where('end_time', '>=', $availability->end_time);
                    });
            })
            ->exists();

        if ($hasBookings) {
            throw new InvalidArgumentException('Cannot delete availability slot with existing bookings.');
        }

        return $availability->delete();
    }

    /**
     * Validate that start time is before end time.
     *
     * @throws InvalidArgumentException
     */
    protected function validateTimeRange(Carbon $startTime, Carbon $endTime): void
    {
        if ($startTime->greaterThanOrEqualTo($endTime)) {
            throw new InvalidArgumentException('Start time must be before end time.');
        }
    }

    /**
     * Validate that there are no overlapping availability slots.
     *
     * @throws InvalidArgumentException
     */
    protected function validateNoOverlappingSlots(
        int $tutorId,
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeId = null
    ): void {
        $query = TutorAvailability::where('tutor_id', $tutorId)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('This time slot overlaps with an existing availability slot.');
        }
    }

    /**
     * Get all availabilities for a tutor within a date range.
     */
    public function getTutorAvailabilities(int $tutorId, Carbon $startDate, Carbon $endDate): array
    {
        return TutorAvailability::where('tutor_id', $tutorId)
            ->betweenDates($startDate, $endDate)
            ->orderBy('start_time')
            ->get()
            ->toArray();
    }
}
