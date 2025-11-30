<?php

namespace App\Http\Controllers;

use App\Dto\Booking\CreateBookingDto;
use App\Dto\Booking\RescheduleBookingDto;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\RescheduleBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * Display a listing of bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Booking::query()->with(['student', 'tutor']);

        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } elseif ($user->isTutor()) {
            $query->where('tutor_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $query->betweenDates($startDate, $endDate);
        }

        $bookings = $query->orderBy('start_time', 'desc')->paginate(15);

        return $this->paginatedResponse($bookings, 'Bookings retrieved successfully');
    }

    /**
     * Store a newly created booking.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            $dto = CreateBookingDto::fromArray([
                'student_id' => $request->user()->id,
                'tutor_id' => $request->tutor_id,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'notes' => $request->notes,
            ]);

            $booking = $this->bookingService->createBooking($dto);
            $booking->load(['student', 'tutor']);

            return $this->createdResponse($booking, 'Booking created successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create booking: '.$e->getMessage(), 500);
        }
    }

    /**
     * Display the specified booking.
     */
    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $booking->load(['student', 'tutor']);

        return $this->successResponse($booking, 'Booking retrieved successfully');
    }

    /**
     * Confirm a booking (tutors only).
     */
    public function confirm(Booking $booking): JsonResponse
    {
        $this->authorize('confirm', $booking);

        try {
            $confirmedBooking = $this->bookingService->confirmBooking($booking);
            $confirmedBooking->load(['student', 'tutor']);

            return $this->successResponse($confirmedBooking, 'Booking confirmed successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to confirm booking: '.$e->getMessage(), 500);
        }
    }

    /**
     * Complete a booking (tutors only).
     */
    public function complete(Booking $booking): JsonResponse
    {
        $this->authorize('complete', $booking);

        try {
            $completedBooking = $this->bookingService->completeBooking($booking);
            $completedBooking->load(['student', 'tutor']);

            return $this->successResponse($completedBooking, 'Booking completed successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to complete booking: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get available slots for a tutor.
     */
    public function availableSlots(Request $request, int $tutorId): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $slots = $this->bookingService->getAvailableSlots($tutorId, $startDate, $endDate);

            return $this->successResponse($slots, 'Available slots retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve available slots: '.$e->getMessage(), 500);
        }
    }

    /**
     * Cancel a booking.
     */
    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->authorize('cancel', $booking);

        try {
            $cancelledBooking = $this->bookingService->cancelBooking($booking);
            $cancelledBooking->load(['student', 'tutor']);

            return $this->successResponse($cancelledBooking, 'Booking cancelled successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to cancel booking: '.$e->getMessage(), 500);
        }
    }

    /**
     * Reschedule a booking.
     */
    public function reschedule(RescheduleBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->authorize('reschedule', $booking);

        try {
            $dto = RescheduleBookingDto::fromRequest($request->validated());
            $rescheduledBooking = $this->bookingService->rescheduleBooking($booking, $dto);
            $rescheduledBooking->load(['student', 'tutor']);

            return $this->successResponse($rescheduledBooking, 'Booking rescheduled successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reschedule booking: '.$e->getMessage(), 500);
        }
    }
}
