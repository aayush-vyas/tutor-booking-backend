<?php

namespace Tests\Feature;

use App\Models\TutorAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function createStudent(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    protected function createTutor(): User
    {
        return User::factory()->create(['role' => 'tutor']);
    }

    public function test_tutor_can_create_availability_slot(): void
    {
        $tutor = $this->createTutor();

        $response = $this->actingAs($tutor, 'sanctum')
            ->postJson('/api/availabilities', [
                'start_time' => '2025-12-15 09:00:00',
                'end_time' => '2025-12-15 17:00:00',
                'is_available' => true,
                'notes' => 'Available for sessions',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'tutor_id',
                    'start_time',
                    'end_time',
                    'is_available',
                    'notes',
                ],
            ]);

        $this->assertDatabaseHas('tutor_availabilities', [
            'tutor_id' => $tutor->id,
            'is_available' => true,
        ]);
    }

    public function test_student_cannot_create_availability_slot(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/availabilities', [
                'start_time' => '2025-12-15 09:00:00',
                'end_time' => '2025-12-15 17:00:00',
                'is_available' => true,
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_create_availability(): void
    {
        $response = $this->postJson('/api/availabilities', [
            'start_time' => '2025-12-15 09:00:00',
            'end_time' => '2025-12-15 17:00:00',
            'is_available' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_tutor_cannot_create_overlapping_availability(): void
    {
        $tutor = $this->createTutor();

        TutorAvailability::create([
            'tutor_id' => $tutor->id,
            'start_time' => '2025-12-15 09:00:00',
            'end_time' => '2025-12-15 17:00:00',
            'is_available' => true,
        ]);

        $response = $this->actingAs($tutor, 'sanctum')
            ->postJson('/api/availabilities', [
                'start_time' => '2025-12-15 10:00:00',
                'end_time' => '2025-12-15 16:00:00',
                'is_available' => true,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This time slot overlaps with an existing availability slot.',
            ]);
    }

    public function test_authenticated_user_can_list_availabilities(): void
    {
        $tutor = $this->createTutor();
        $student = $this->createStudent();

        TutorAvailability::factory()->count(3)->create([
            'tutor_id' => $tutor->id,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/availabilities');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'tutor_id',
                        'start_time',
                        'end_time',
                        'is_available',
                        'tutor',
                    ],
                ],
                'pagination',
            ]);
    }

    public function test_authenticated_user_can_view_specific_availability(): void
    {
        $tutor = $this->createTutor();
        $student = $this->createStudent();

        $availability = TutorAvailability::factory()->create([
            'tutor_id' => $tutor->id,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->getJson("/api/availabilities/{$availability->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'tutor_id',
                    'start_time',
                    'end_time',
                    'is_available',
                    'tutor',
                ],
            ]);
    }

    public function test_tutor_can_update_their_availability(): void
    {
        $tutor = $this->createTutor();

        $availability = TutorAvailability::factory()->create([
            'tutor_id' => $tutor->id,
            'start_time' => '2025-12-15 09:00:00',
            'end_time' => '2025-12-15 17:00:00',
        ]);

        $response = $this->actingAs($tutor, 'sanctum')
            ->putJson("/api/availabilities/{$availability->id}", [
                'start_time' => '2025-12-15 10:00:00',
                'end_time' => '2025-12-15 18:00:00',
                'is_available' => false,
                'notes' => 'Updated notes',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Availability updated successfully',
            ]);

        $this->assertDatabaseHas('tutor_availabilities', [
            'id' => $availability->id,
            'start_time' => '2025-12-15 10:00:00',
            'is_available' => false,
        ]);
    }

    public function test_student_cannot_update_availability(): void
    {
        $tutor = $this->createTutor();
        $student = $this->createStudent();

        $availability = TutorAvailability::factory()->create([
            'tutor_id' => $tutor->id,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->putJson("/api/availabilities/{$availability->id}", [
                'start_time' => '2025-12-15 10:00:00',
                'end_time' => '2025-12-15 18:00:00',
                'is_available' => false,
            ]);

        $response->assertStatus(403);
    }

    public function test_tutor_can_delete_their_availability(): void
    {
        $tutor = $this->createTutor();

        $availability = TutorAvailability::factory()->create([
            'tutor_id' => $tutor->id,
        ]);

        $response = $this->actingAs($tutor, 'sanctum')
            ->deleteJson("/api/availabilities/{$availability->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Availability deleted successfully',
            ]);

        $this->assertDatabaseMissing('tutor_availabilities', [
            'id' => $availability->id,
        ]);
    }

    public function test_student_cannot_delete_availability(): void
    {
        $tutor = $this->createTutor();
        $student = $this->createStudent();

        $availability = TutorAvailability::factory()->create([
            'tutor_id' => $tutor->id,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->deleteJson("/api/availabilities/{$availability->id}");

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_get_tutor_schedule(): void
    {
        $tutor = $this->createTutor();
        $student = $this->createStudent();

        TutorAvailability::factory()->count(5)->create([
            'tutor_id' => $tutor->id,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->getJson("/api/tutors/{$tutor->id}/schedule");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'tutor_id',
                        'start_time',
                        'end_time',
                        'is_available',
                    ],
                ],
            ]);
    }

    public function test_create_availability_fails_with_invalid_dates(): void
    {
        $tutor = $this->createTutor();

        $response = $this->actingAs($tutor, 'sanctum')
            ->postJson('/api/availabilities', [
                'start_time' => 'invalid-date',
                'end_time' => 'invalid-date',
                'is_available' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_time', 'end_time']);
    }

    public function test_create_availability_fails_when_end_time_before_start_time(): void
    {
        $tutor = $this->createTutor();

        $response = $this->actingAs($tutor, 'sanctum')
            ->postJson('/api/availabilities', [
                'start_time' => '2025-12-15 17:00:00',
                'end_time' => '2025-12-15 09:00:00',
                'is_available' => true,
            ]);

        $response->assertStatus(422);
    }
}
