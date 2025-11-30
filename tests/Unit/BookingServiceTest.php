<?php

namespace Tests\Unit;

use App\Dto\Booking\CreateBookingDto;
use App\Models\Booking;
use App\Models\TutorAvailability;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BookingService $bookingService;

    protected User $student;

    protected User $tutor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookingService = app(BookingService::class);

        // Create test users
        $this->student = User::factory()->create(['role' => 'student']);
        $this->tutor = User::factory()->create(['role' => 'tutor']);
    }

    /**
     * Test creating a valid booking.
     */
    public function test_can_create_valid_booking(): void
    {
        // Create availability slot
        $startTime = Carbon::now()->addDay()->setTime(10, 0);
        $endTime = Carbon::now()->addDay()->setTime(12, 0);

        TutorAvailability::create([
            'tutor_id' => $this->tutor->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_available' => true,
        ]);

        // Create booking DTO
        $bookingStart = Carbon::now()->addDay()->setTime(10, 30);
        $bookingEnd = Carbon::now()->addDay()->setTime(11, 30);

        $dto = new CreateBookingDto(
            studentId: $this->student->id,
            tutorId: $this->tutor->id,
            startTime: $bookingStart,
            endTime: $bookingEnd
        );

        $booking = $this->bookingService->createBooking($dto);

        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals('pending', $booking->status);
        $this->assertEquals($this->student->id, $booking->student_id);
        $this->assertEquals($this->tutor->id, $booking->tutor_id);
    }

    /**
     * Test that bookings cannot overlap.
     */
    public function test_cannot_create_overlapping_booking(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('overlaps with an existing booking');

        // Create availability slot
        $startTime = Carbon::now()->addDay()->setTime(10, 0);
        $endTime = Carbon::now()->addDay()->setTime(14, 0);

        TutorAvailability::create([
            'tutor_id' => $this->tutor->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_available' => true,
        ]);

        // Create first booking
        $booking1Start = Carbon::now()->addDay()->setTime(11, 0);
        $booking1End = Carbon::now()->addDay()->setTime(12, 0);

        Booking::create([
            'student_id' => $this->student->id,
            'tutor_id' => $this->tutor->id,
            'start_time' => $booking1Start,
            'end_time' => $booking1End,
            'status' => 'confirmed',
        ]);

        // Try to create overlapping booking
        $booking2Start = Carbon::now()->addDay()->setTime(11, 30);
        $booking2End = Carbon::now()->addDay()->setTime(12, 30);

        $dto = new CreateBookingDto(
            studentId: $this->student->id,
            tutorId: $this->tutor->id,
            startTime: $booking2Start,
            endTime: $booking2End
        );

        $this->bookingService->createBooking($dto);
    }

    /**
     * Test that booking must fall within available slot.
     */
    public function test_booking_must_be_within_available_slot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must fall completely within an available slot');

        // Create availability slot from 10:00 to 12:00
        $startTime = Carbon::now()->addDay()->setTime(10, 0);
        $endTime = Carbon::now()->addDay()->setTime(12, 0);

        TutorAvailability::create([
            'tutor_id' => $this->tutor->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_available' => true,
        ]);

        // Try to book from 11:30 to 13:00 (extends beyond available slot)
        $bookingStart = Carbon::now()->addDay()->setTime(11, 30);
        $bookingEnd = Carbon::now()->addDay()->setTime(13, 0);

        $dto = new CreateBookingDto(
            studentId: $this->student->id,
            tutorId: $this->tutor->id,
            startTime: $bookingStart,
            endTime: $bookingEnd
        );

        $this->bookingService->createBooking($dto);
    }

    /**
     * Test that booking cannot intersect with blocked slots.
     */
    public function test_booking_cannot_intersect_blocked_slots(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('intersects with a blocked period');

        // Create available slot
        TutorAvailability::create([
            'tutor_id' => $this->tutor->id,
            'start_time' => Carbon::now()->addDay()->setTime(10, 0),
            'end_time' => Carbon::now()->addDay()->setTime(14, 0),
            'is_available' => true,
        ]);

        // Create blocked slot from 11:00 to 12:00
        TutorAvailability::create([
            'tutor_id' => $this->tutor->id,
            'start_time' => Carbon::now()->addDay()->setTime(11, 0),
            'end_time' => Carbon::now()->addDay()->setTime(12, 0),
            'is_available' => false,
        ]);

        // Try to book from 10:30 to 11:30 (intersects with blocked slot)
        $bookingStart = Carbon::now()->addDay()->setTime(10, 30);
        $bookingEnd = Carbon::now()->addDay()->setTime(11, 30);

        $dto = new CreateBookingDto(
            studentId: $this->student->id,
            tutorId: $this->tutor->id,
            startTime: $bookingStart,
            endTime: $bookingEnd
        );

        $this->bookingService->createBooking($dto);
    }

    /**
     * Test that booking cannot be in the past.
     */
    public function test_cannot_book_in_the_past(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot book in the past');

        $bookingStart = Carbon::now()->subDay();
        $bookingEnd = Carbon::now()->subDay()->addHour();

        $dto = new CreateBookingDto(
            studentId: $this->student->id,
            tutorId: $this->tutor->id,
            startTime: $bookingStart,
            endTime: $bookingEnd
        );

        $this->bookingService->createBooking($dto);
    }

    /**
     * Test that end time must be after start time.
     */
    public function test_end_time_must_be_after_start_time(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start time must be before end time');

        $bookingStart = Carbon::now()->addDay()->setTime(12, 0);
        $bookingEnd = Carbon::now()->addDay()->setTime(11, 0);

        $dto = new CreateBookingDto(
            studentId: $this->student->id,
            tutorId: $this->tutor->id,
            startTime: $bookingStart,
            endTime: $bookingEnd
        );

        $this->bookingService->createBooking($dto);
    }
}
