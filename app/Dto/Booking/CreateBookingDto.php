<?php

namespace App\Dto\Booking;

use Carbon\Carbon;

class CreateBookingDto
{
    public function __construct(
        public readonly int $studentId,
        public readonly int $tutorId,
        public readonly Carbon $startTime,
        public readonly Carbon $endTime,
        public readonly ?string $notes = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            studentId: $data['student_id'],
            tutorId: $data['tutor_id'],
            startTime: Carbon::parse($data['start_time']),
            endTime: Carbon::parse($data['end_time']),
            notes: $data['notes'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'student_id' => $this->studentId,
            'tutor_id' => $this->tutorId,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'notes' => $this->notes,
            'status' => 'pending',
        ];
    }
}
