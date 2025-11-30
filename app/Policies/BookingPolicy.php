<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Booking $booking): bool
    {
        return $booking->student_id === $user->id || $booking->tutor_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isStudent();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Booking $booking): bool
    {
        return $booking->student_id === $user->id || $booking->tutor_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Booking $booking): bool
    {
        return $booking->student_id === $user->id || $booking->tutor_id === $user->id;
    }

    /**
     * Determine whether the tutor can confirm the booking.
     */
    public function confirm(User $user, Booking $booking): bool
    {
        return $user->isTutor() && $booking->tutor_id === $user->id;
    }

    /**
     * Determine whether the tutor can complete the booking.
     */
    public function complete(User $user, Booking $booking): bool
    {
        return $user->isTutor() && $booking->tutor_id === $user->id;
    }

    /**
     * Determine whether the user can cancel the booking.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->student_id === $user->id || $booking->tutor_id === $user->id;
    }

    /**
     * Determine whether the user can reschedule the booking.
     */
    public function reschedule(User $user, Booking $booking): bool
    {
        return $booking->student_id === $user->id || $booking->tutor_id === $user->id;
    }
}
