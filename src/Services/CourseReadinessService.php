<?php

namespace Lalalili\CourseCore\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lalalili\CourseCore\Contracts\CourseProductResolver;
use Lalalili\CourseCore\Data\CourseReadinessResult;

class CourseReadinessService
{
    public function __construct(private readonly CourseProductResolver $productResolver)
    {
    }

    public function evaluate(
        Model $course,
        bool $requireProduct = false,
        bool $requireReadyVideos = false,
    ): CourseReadinessResult {
        $blockingIssues = [];
        $warnings = [];
        $suggestions = [];

        $this->checkBasicFields($course, $blockingIssues);
        $this->checkDetail($course, $blockingIssues, $suggestions);
        $this->checkProduct($course, $blockingIssues, $requireProduct);
        $this->checkUnits($course, $blockingIssues, $warnings, $requireReadyVideos);

        return new CourseReadinessResult(
            blockingIssues: array_values(array_unique($blockingIssues)),
            warnings: array_values(array_unique($warnings)),
            suggestions: array_values(array_unique($suggestions)),
        );
    }

    /**
     * @param  list<string>  $blockingIssues
     */
    private function checkBasicFields(Model $course, array &$blockingIssues): void
    {
        if (blank(data_get($course, 'title')) && blank(data_get($course, 'name'))) {
            $blockingIssues[] = 'Course title is required.';
        }

        if (
            blank(data_get($course, 'course_category_id'))
            && blank(data_get($course, 'category_id'))
            && ! $this->relation($course, 'category') instanceof Model
        ) {
            $blockingIssues[] = 'Course category is required.';
        }
    }

    /**
     * @param  list<string>  $blockingIssues
     * @param  list<string>  $suggestions
     */
    private function checkDetail(Model $course, array &$blockingIssues, array &$suggestions): void
    {
        $detail = $this->relation($course, 'detail');

        if (! $detail instanceof Model) {
            $blockingIssues[] = 'Course detail is required.';

            return;
        }

        if (
            blank(data_get($detail, 'content'))
            && blank(data_get($detail, 'description'))
            && blank(data_get($detail, 'product_desc'))
        ) {
            $suggestions[] = 'Course detail content is empty.';
        }
    }

    /**
     * @param  list<string>  $blockingIssues
     */
    private function checkProduct(Model $course, array &$blockingIssues, bool $requireProduct): void
    {
        if (! $requireProduct) {
            return;
        }

        if (! $this->productResolver->productForCourse($course) instanceof Model) {
            $blockingIssues[] = 'Course product binding is required.';
        }
    }

    /**
     * @param  list<string>  $blockingIssues
     * @param  list<string>  $warnings
     */
    private function checkUnits(
        Model $course,
        array &$blockingIssues,
        array &$warnings,
        bool $requireReadyVideos,
    ): void {
        $chapters = $this->collectionRelation($course, 'chapters');

        if ($chapters->isEmpty()) {
            $blockingIssues[] = 'At least one course chapter is required.';

            return;
        }

        $units = $chapters->flatMap(fn (Model $chapter): Collection => $this->collectionRelation($chapter, 'units'));

        if ($units->isEmpty()) {
            $blockingIssues[] = 'At least one course unit is required.';

            return;
        }

        $units->each(function (Model $unit) use (&$blockingIssues, &$warnings, $requireReadyVideos): void {
            $title = (string) (data_get($unit, 'title') ?: 'Untitled unit');
            $video = $this->relation($unit, 'video');
            $hasVideoReference = filled(data_get($unit, 'video_url'))
                || filled(data_get($unit, 'url'))
                || filled(data_get($unit, 'provider_video_id'))
                || $video instanceof Model;

            if (! $hasVideoReference) {
                $blockingIssues[] = "Course unit [{$title}] is missing a video.";

                return;
            }

            if (! $video instanceof Model) {
                return;
            }

            if ($this->isVideoReady($video)) {
                return;
            }

            if ($requireReadyVideos) {
                $blockingIssues[] = "Course unit [{$title}] video is not ready.";

                return;
            }

            $warnings[] = "Course unit [{$title}] video is still processing.";
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

    private function relation(Model $model, string $name): mixed
    {
        if ($model->relationLoaded($name)) {
            return $model->getRelation($name);
        }

        return null;
    }

    /**
     * @return Collection<int, Model>
     */
    private function collectionRelation(Model $model, string $name): Collection
    {
        $relation = $this->relation($model, $name);

        if ($relation instanceof Collection) {
            return $relation->filter(fn (mixed $item): bool => $item instanceof Model)->values();
        }

        return collect();
    }
}
