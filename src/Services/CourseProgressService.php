<?php

namespace Lalalili\CourseCore\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Lalalili\CourseCore\Exceptions\CourseConfigurationException;

class CourseProgressService
{
    /** @return class-string<Model> */
    protected function historyModelClass(): string
    {
        $model = config('course-core.models.history');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw CourseConfigurationException::missingModel('history');
        }

        return $model;
    }

    /**
     * Update or create a unit progress record.
     *
     * Once a unit is marked completed, the completed flag cannot be rolled back
     * (e.g. if the client sends completed=false after sending completed=true).
     */
    public function sync(
        int $userId,
        int $courseId,
        int $chapterId,
        int $unitId,
        float $progress,
        ?string $lastWatchedAt,
        bool $completed,
    ): void {
        $historyClass = $this->historyModelClass();

        $existing = $historyClass::where([
            'user_id' => $userId,
            'course_id' => $courseId,
            'chapter_id' => $chapterId,
            'unit_id' => $unitId,
        ])->first();

        if ($existing && (bool) data_get($existing, 'completed')) {
            $completed = true;
        }

        $historyClass::updateOrCreate(
            [
                'user_id' => $userId,
                'course_id' => $courseId,
                'chapter_id' => $chapterId,
                'unit_id' => $unitId,
            ],
            [
                'progress' => $progress,
                'last_watched_at' => $lastWatchedAt ? Carbon::parse($lastWatchedAt) : null,
                'completed' => $completed,
            ]
        );
    }

    /**
     * Fetch a single unit progress record. Returns null if no record exists yet.
     */
    public function fetch(int $userId, int $courseId, int $chapterId, int $unitId): ?Model
    {
        $historyClass = $this->historyModelClass();

        return $historyClass::where([
            'user_id' => $userId,
            'course_id' => $courseId,
            'chapter_id' => $chapterId,
            'unit_id' => $unitId,
        ])->first();
    }
}
