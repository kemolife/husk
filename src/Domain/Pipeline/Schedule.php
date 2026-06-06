<?php

namespace App\Domain\Pipeline;

final readonly class Schedule
{
    public function __construct(
        public readonly string $cron,
        public readonly string $environment = 'production',
    ) {}

    public function isDue(\DateTimeImmutable $now): bool
    {
        $parts = explode(' ', trim($this->cron));
        if (count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        return $this->fieldMatches($minute, (int) $now->format('i'))
            && $this->fieldMatches($hour, (int) $now->format('G'))
            && $this->fieldMatches($day, (int) $now->format('j'))
            && $this->fieldMatches($month, (int) $now->format('n'))
            && $this->fieldMatches($weekday, (int) $now->format('w'));
    }

    private function fieldMatches(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        if (str_contains($field, '/')) {
            [, $step] = explode('/', $field, 2);
            return (int) $step > 0 && $value % (int) $step === 0;
        }

        if (str_contains($field, ',')) {
            return in_array($value, array_map('intval', explode(',', $field)), true);
        }

        if (str_contains($field, '-')) {
            [$from, $to] = explode('-', $field, 2);
            return $value >= (int) $from && $value <= (int) $to;
        }

        return $value === (int) $field;
    }
}
