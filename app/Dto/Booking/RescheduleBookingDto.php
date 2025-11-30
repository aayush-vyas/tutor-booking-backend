<?php

namespace App\Dto\Booking;

use Carbon\Carbon;

class RescheduleBookingDto
{
    public function __construct(
        public readonly Carbon $startTime,
        public readonly Carbon $endTime,
        public readonly ?string $notes = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            startTime: Carbon::parse($data['start_time']),
            endTime: Carbon::parse($data['end_time']),
            notes: $data['notes'] ?? null,
        );
    }
}
