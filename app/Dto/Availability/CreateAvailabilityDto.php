<?php

namespace App\Dto\Availability;

use Carbon\Carbon;

class CreateAvailabilityDto
{
    public function __construct(
        public readonly int $tutorId,
        public readonly Carbon $startTime,
        public readonly Carbon $endTime,
        public readonly bool $isAvailable = true,
        public readonly ?string $notes = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tutorId: $data['tutor_id'],
            startTime: Carbon::parse($data['start_time']),
            endTime: Carbon::parse($data['end_time']),
            isAvailable: $data['is_available'] ?? true,
            notes: $data['notes'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'tutor_id' => $this->tutorId,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'is_available' => $this->isAvailable,
            'notes' => $this->notes,
        ];
    }
}
