<?php

namespace Lalalili\CourseCore\Readiness;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lalalili\CourseCore\Contracts\CourseReadinessCheck;
use Lalalili\CourseCore\Data\CourseReadinessContext;
use Lalalili\CourseCore\Support\CourseReadinessReport;

class UnitsCheck implements CourseReadinessCheck
{
    public function check(Model $course, CourseReadinessReport $report, CourseReadinessContext $context): void
    {
        $chapters = $this->collectionRelation($course, 'chapters');

        if ($chapters->isEmpty()) {
            $report->addBlockingIssue('At least one course chapter is required.');

            return;
        }

        $units = $chapters->flatMap(fn (Model $chapter): Collection => $this->collectionRelation($chapter, 'units'));

        if ($units->isEmpty()) {
            $report->addBlockingIssue('At least one course unit is required.');

            return;
        }

        $units->each(function (Model $unit) use ($report, $context): void {
            $title = (string) (data_get($unit, 'title') ?: 'Untitled unit');
            $video = $unit->relationLoaded('video') ? $unit->getRelation('video') : null;
            $hasVideoReference = filled(data_get($unit, 'video_url'))
                || filled(data_get($unit, 'url'))
                || filled(data_get($unit, 'provider_video_id'))
                || $video instanceof Model;

            if (! $hasVideoReference) {
                $report->addBlockingIssue("Course unit [{$title}] is missing a video.");

                return;
            }

            if (! $video instanceof Model) {
                return;
            }

            if ($this->isVideoReady($video)) {
                return;
            }

            if ($context->requireReadyVideos) {
                $report->addBlockingIssue("Course unit [{$title}] video is not ready.");

                return;
            }

            $report->addWarning("Course unit [{$title}] video is still processing.");
        });
    }

    private function isVideoReady(Model $video): bool
    {
        $transcodeStatus = (string) data_get($video, 'transcode_status', '');
        $providerStatus = (string) data_get($video, 'provider_status', '');
        $status = (string) data_get($video, 'status', '');

        return in_array($transcodeStatus, ['ready', 'complete', 'completed'], true)
            || in_array($providerStatus, ['ready', 'complete', 'completed'], true)
            || in_array($status, ['ready', 'complete', 'completed', '1'], true);
    }

    /**
     * @return Collection<int, Model>
     */
    private function collectionRelation(Model $model, string $name): Collection
    {
        if ($model->relationLoaded($name)) {
            $relation = $model->getRelation($name);

            if ($relation instanceof Collection) {
                return $relation->filter(fn (mixed $item): bool => $item instanceof Model)->values();
            }
        }

        return collect();
    }
}
