<?php

namespace App\Domain\Pipeline;

final class Pipeline
{
    /**
     * @param Job[] $jobs
     * @param Schedule[] $schedules
     */
    public function __construct(
        private readonly PipelineId $id,
        private readonly string $name,
        private readonly array $jobs,
        private readonly ?NotificationConfig $notifications = null,
        private readonly array $schedules = [],
    ) {}

    public function id(): PipelineId { return $this->id; }
    public function name(): string { return $this->name; }
    /** @return Job[] */
    public function jobs(): array { return $this->jobs; }
    public function notifications(): ?NotificationConfig { return $this->notifications; }
    /** @return Schedule[] */
    public function schedules(): array { return $this->schedules; }

    public function job(string $jobId): Job
    {
        foreach ($this->jobs as $job) {
            if ($job->id === $jobId) {
                return $job;
            }
        }
        throw new \InvalidArgumentException("Job '{$jobId}' not found in pipeline '{$this->id->value}'");
    }
}
