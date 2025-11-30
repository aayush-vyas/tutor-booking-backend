<?php

namespace App\Http\Controllers;

use App\Dto\Availability\CreateAvailabilityDto;
use App\Http\Requests\Availability\StoreAvailabilityRequest;
use App\Http\Requests\Availability\UpdateAvailabilityRequest;
use App\Models\TutorAvailability;
use App\Services\AvailabilityService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Display a listing of availabilities.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = TutorAvailability::query()->with('tutor');

        if ($user->isTutor()) {
            $query->where('tutor_id', $user->id);
        } elseif ($request->has('tutor_id')) {
            $query->where('tutor_id', $request->tutor_id);
        }

        if ($request->has('is_available')) {
            $query->where('is_available', $request->boolean('is_available'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $query->betweenDates($startDate, $endDate);
        }

        $availabilities = $query->orderBy('start_time')->paginate(15);

        return $this->paginatedResponse($availabilities, 'Availabilities retrieved successfully');
    }

    /**
     * Store a newly created availability.
     */
    public function store(StoreAvailabilityRequest $request): JsonResponse
    {
        try {
            $dto = CreateAvailabilityDto::fromArray([
                'tutor_id' => $request->user()->id,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'is_available' => $request->is_available ?? true,
                'notes' => $request->notes,
            ]);

            $availability = $this->availabilityService->createAvailability($dto);
            $availability->load('tutor');

            return $this->createdResponse($availability, 'Availability created successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create availability: '.$e->getMessage(), 500);
        }
    }

    /**
     * Display the specified availability.
     */
    public function show(TutorAvailability $availability): JsonResponse
    {
        $availability->load('tutor');

        return $this->successResponse($availability, 'Availability retrieved successfully');
    }

    /**
     * Update the specified availability.
     */
    public function update(UpdateAvailabilityRequest $request, TutorAvailability $availability): JsonResponse
    {
        try {
            $startTime = Carbon::parse($request->start_time);
            $endTime = Carbon::parse($request->end_time);

            $updatedAvailability = $this->availabilityService->updateAvailability(
                $availability,
                $startTime,
                $endTime,
                $request->is_available ?? $availability->is_available,
                $request->notes
            );

            $updatedAvailability->load('tutor');

            return $this->successResponse($updatedAvailability, 'Availability updated successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update availability: '.$e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified availability.
     */
    public function destroy(TutorAvailability $availability): JsonResponse
    {
        $user = request()->user();

        if ($availability->tutor_id !== $user->id) {
            return $this->forbiddenResponse('You do not have permission to delete this availability.');
        }

        try {
            $this->availabilityService->deleteAvailability($availability);

            return $this->successResponse(null, 'Availability deleted successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete availability: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get tutor's schedule (availabilities for a specific tutor).
     */
    public function tutorSchedule(Request $request, int $tutorId): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $schedule = $this->availabilityService->getTutorAvailabilities($tutorId, $startDate, $endDate);

            return $this->successResponse($schedule, 'Tutor schedule retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve tutor schedule: '.$e->getMessage(), 500);
        }
    }
}
