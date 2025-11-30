<?php

namespace App\Services;

use App\Dto\Booking\CreateBookingDto;
use App\Dto\Booking\RescheduleBookingDto;
use App\Models\Booking;
use App\Models\TutorAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingService
{
    /**
     * Create a new booking with validation.
     *
     * @throws InvalidArgumentException
     */
    public function createBooking(CreateBookingDto $dto): Booking
    {
        $this->validateTimeRange($dto->startTime, $dto->endTime);
        $this->validateTutorAvailability($dto->tutorId, $dto->startTime, $dto->endTime);
        $this->validateNoOverlappingBookings($dto->tutorId, $dto->startTime, $dto->endTime);
        $this->validateNoBlockedSlots($dto->tutorId, $dto->startTime, $dto->endTime);

        return DB::transaction(function () use ($dto) {
            return Booking::create($dto->toArray());
        });
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

        if ($startTime->isPast()) {
            throw new InvalidArgumentException('Cannot book in the past.');
        }
    }

    /**
     * Validate that the booking falls within tutor's available slots.
     *
     * @throws InvalidArgumentException
     */
    protected function validateTutorAvailability(int $tutorId, Carbon $startTime, Carbon $endTime): void
    {
        $availableSlots = TutorAvailability::where('tutor_id', $tutorId)
            ->available()
            ->betweenDates($startTime, $endTime)
            ->get();

        if ($availableSlots->isEmpty()) {
            throw new InvalidArgumentException('No available slots found for the requested time range.');
        }

        $isCovered = $availableSlots->contains(function ($slot) use ($startTime, $endTime) {
            return $slot->start_time->lessThanOrEqualTo($startTime)
                && $slot->end_time->greaterThanOrEqualTo($endTime);
        });

        if (! $isCovered) {
            throw new InvalidArgumentException('The booking time must fall completely within an available slot.');
        }
    }

    /**
     * Validate that there are no overlapping bookings for the tutor.
     *
     * @throws InvalidArgumentException
     */
    protected function validateNoOverlappingBookings(int $tutorId, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId = null): void
    {
        $query = Booking::where('tutor_id', $tutorId)
            ->active()
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('This time slot overlaps with an existing booking.');
        }
    }

    /**
     * Validate that there are no blocked slots intersecting with the booking.
     *
     * @throws InvalidArgumentException
     */
    protected function validateNoBlockedSlots(int $tutorId, Carbon $startTime, Carbon $endTime): void
    {
        $blockedSlots = TutorAvailability::where('tutor_id', $tutorId)
            ->blocked()
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            });

        if ($blockedSlots->exists()) {
            throw new InvalidArgumentException('This time slot intersects with a blocked period.');
        }
    }

    /**
     * Confirm a booking.
     *
     * @throws InvalidArgumentException
     */
    public function confirmBooking(Booking $booking): Booking
    {
        if ($booking->status !== 'pending') {
            throw new InvalidArgumentException('Only pending bookings can be confirmed.');
        }

        $booking->update(['status' => 'confirmed']);

        return $booking->fresh();
    }

    /**
     * Complete a booking.
     */
    public function completeBooking(Booking $booking): Booking
    {
        if ($booking->status !== 'confirmed') {
            throw new InvalidArgumentException('Only confirmed bookings can be completed.');
        }

        $booking->update(['status' => 'completed']);

        return $booking->fresh();
    }

    /**
     * Get available slots for a tutor within a date range.
     */
    public function getAvailableSlots(int $tutorId, Carbon $startDate, Carbon $endDate): array
    {
        $availableSlots = TutorAvailability::where('tutor_id', $tutorId)
            ->available()
            ->betweenDates($startDate, $endDate)
            ->orderBy('start_time')
            ->get();

        $bookings = Booking::where('tutor_id', $tutorId)
            ->active()
            ->betweenDates($startDate, $endDate)
            ->get();

        $freeSlots = [];

        foreach ($availableSlots as $slot) {
            $slotStart = $slot->start_time;
            $slotEnd = $slot->end_time;

            $overlappingBookings = $bookings->filter(function ($booking) use ($slotStart, $slotEnd) {
                return $booking->overlaps($slotStart, $slotEnd);
            })->sortBy('start_time');

            if ($overlappingBookings->isEmpty()) {
                $freeSlots[] = [
                    'start_time' => $slotStart,
                    'end_time' => $slotEnd,
                ];
            } else {
                $currentStart = $slotStart;

                foreach ($overlappingBookings as $booking) {
                    if ($currentStart->lessThan($booking->start_time)) {
                        $freeSlots[] = [
                            'start_time' => $currentStart,
                            'end_time' => $booking->start_time,
                        ];
                    }

                    $currentStart = $booking->end_time->greaterThan($currentStart)
                        ? $booking->end_time
                        : $currentStart;
                }

                if ($currentStart->lessThan($slotEnd)) {
                    $freeSlots[] = [
                        'start_time' => $currentStart,
                        'end_time' => $slotEnd,
                    ];
                }
            }
        }

        return $freeSlots;
    }

    /**
     * Cancel a booking.
     *
     * @throws InvalidArgumentException
     */
    public function cancelBooking(Booking $booking): Booking
    {
        if (! in_array($booking->status, ['pending', 'confirmed'])) {
            throw new InvalidArgumentException('Only pending or confirmed bookings can be cancelled.');
        }

        $hoursUntilStart = now()->diffInHours($booking->start_time, false);
        if ($hoursUntilStart < 24 && $hoursUntilStart > 0) {
            throw new InvalidArgumentException('Cannot cancel booking less than 24 hours before start time.');
        }

        $booking->update(['status' => 'cancelled']);

        return $booking->fresh();
    }

    /**
     * Reschedule a booking to a new time.
     *
     * @throws InvalidArgumentException
     */
    public function rescheduleBooking(Booking $booking, RescheduleBookingDto $dto): Booking
    {
        if (! in_array($booking->status, ['pending', 'confirmed'])) {
            throw new InvalidArgumentException('Only pending or confirmed bookings can be rescheduled.');
        }

        $this->validateTimeRange($dto->startTime, $dto->endTime);
        $this->validateTutorAvailability($booking->tutor_id, $dto->startTime, $dto->endTime);
        $this->validateNoOverlappingBookings(
            $booking->tutor_id,
            $dto->startTime,
            $dto->endTime,
            $booking->id
        );
        $this->validateNoBlockedSlots($booking->tutor_id, $dto->startTime, $dto->endTime);

        return DB::transaction(function () use ($booking, $dto) {
            $updateData = [
                'start_time' => $dto->startTime,
                'end_time' => $dto->endTime,
                'status' => 'pending',
            ];

            if ($dto->notes !== null) {
                $updateData['notes'] = $dto->notes;
            }

            $booking->update($updateData);

            return $booking->fresh();
        });
    }
}
