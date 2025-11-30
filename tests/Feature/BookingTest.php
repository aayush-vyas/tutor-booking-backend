<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\TutorAvailability;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $tutor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => 'student']);
        $this->tutor = User::factory()->create(['role' => 'tutor']);
    }

    /**
     * Test student can create a booking.
     */
    public function test_student_can_create_booking(): void
    {
        Sanctum::actingAs($this->student);

        // Create availability
        TutorAvailability::create([
            'tutor_id' => $this->tutor->id,
            'start_time' => Carbon::now()->addDay()->setTime(10, 0),
            'end_time' => Carbon::now()->addDay()->setTime(12, 0),
            'is_available' => true,
        ]);

        $response = $this->postJson('/api/bookings', [
            'tutor_id' => $this->tutor->id,
            'start_time' => Carbon::now()->addDay()->setTime(10, 30)->toIso8601String(),
            'end_time' => Carbon::now()->addDay()->setTime(11, 30)->toIso8601String(),
            'notes' => 'Test booking',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'student_id',
                    'tutor_id',
                    'start_time',
                    'end_time',
                    'status',
                    'notes',
                ],
            ]);

        $this->assertDatabaseHas('bookings', [
            'student_id' => $this->student->id,
            'tutor_id' => $this->tutor->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Test tutor cannot create a booking.
     */
    public function test_tutor_cannot_create_booking(): void
    {
        Sanctum::actingAs($this->tutor);

        $response = $this->postJson('/api/bookings', [
            'tutor_id' => $this->tutor->id,
            'start_time' => Carbon::now()->addDay()->setTime(10, 30)->toIso8601String(),
            'end_time' => Carbon::now()->addDay()->setTime(11, 30)->toIso8601String(),
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test student can view their bookings.
     */
    public function test_student_can_view_their_bookings(): void
    {
        Sanctum::actingAs($this->student);

        // Create booking
        Booking::create([
            'student_id' => $this->student->id,
            'tutor_id' => $this->tutor->id,
            'start_time' => Carbon::now()->addDay()->setTime(10, 0),
            'end_time' => Carbon::now()->addDay()->setTime(11, 0),
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/bookings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'student_id',
                        'tutor_id',
                        'start_time',
                        'end_time',
                        'status',
                    ],
                ],
            ]);
    }

    /**
     * Test tutor can confirm booking.
     */
    public function test_tutor_can_confirm_booking(): void
    {
        Sanctum::actingAs($this->tutor);

        $booking = Booking::create([
            'student_id' => $this->student->id,
            'tutor_id' => $this->tutor->id,
            'start_time' => Carbon::now()->addDay()->setTime(10, 0),
            'end_time' => Carbon::now()->addDay()->setTime(11, 0),
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/bookings/{$booking->id}/confirm");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'confirmed',
                ],
            ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    /**
     * Test student cannot confirm booking.
     */
    public function test_student_cannot_confirm_booking(): void
    {
        Sanctum::actingAs($this->student);

        $booking = Booking::create([
            'student_id' => $this->student->id,
            'tutor_id' => $this->tutor->id,
            'start_time' => Carbon::now()->addDay()->setTime(10, 0),
            'end_time' => Carbon::now()->addDay()->setTime(11, 0),
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/bookings/{$booking->id}/confirm");

        $response->assertStatus(403);
    }

    /**
     * Test student can cancel their booking.
     */
    public function test_student_can_cancel_their_booking(): void
    {
        Sanctum::actingAs($this->student);

        $booking = Booking::create([
            'student_id' => $this->student->id,
            'tutor_id' => $this->tutor->id,
            'start_time' => Carbon::now()->addDay()->setTime(10, 0),
            'end_time' => Carbon::now()->addDay()->setTime(11, 0),
            'status' => 'pending',
        ]);

        $response = $this->deleteJson("/api/bookings/{$booking->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Test get available slots for tutor.
     */
    public function test_can_get_available_slots_for_tutor(): void
    {
        Sanctum::actingAs($this->student);

        // Create availability
        TutorAvailability::create([
            'tutor_id' => $this->tutor->id,
            'start_time' => Carbon::now()->addDay()->setTime(10, 0),
            'end_time' => Carbon::now()->addDay()->setTime(14, 0),
            'is_available' => true,
        ]);

        // Create a booking
        Booking::create([
            'student_id' => $this->student->id,
            'tutor_id' => $this->tutor->id,
            'start_time' => Carbon::now()->addDay()->setTime(11, 0),
            'end_time' => Carbon::now()->addDay()->setTime(12, 0),
            'status' => 'confirmed',
        ]);

        $response = $this->getJson("/api/tutors/{$this->tutor->id}/available-slots?".http_build_query([
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addDays(2)->toDateString(),
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'start_time',
                        'end_time',
                    ],
                ],
            ]);
    }

    /**
     * Test unauthenticated user cannot access bookings.
     */
    public function test_unauthenticated_user_cannot_access_bookings(): void
    {
        $response = $this->getJson('/api/bookings');

        $response->assertStatus(401);
    }
}
