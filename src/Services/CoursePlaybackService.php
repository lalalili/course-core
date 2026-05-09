<?php

namespace Lalalili\CourseCore\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Lalalili\CourseCore\Contracts\CourseAccessResolver;
use Lalalili\CourseCore\Exceptions\CourseConfigurationException;

class CoursePlaybackService
{
    public function __construct(
        protected CourseAccessResolver $accessResolver,
    ) {
    }

    /**
     * @param  Collection<int, Model>  $chapters
     * @param  Collection<int, Model>  $histories
     */
    public function initializeUnit(Model $course, Collection $chapters, Collection $histories, ?Authenticatable $user = null): ?Model
    {
        $user ??= auth()->user();

        /** @var Collection<int, Model> $units */
        $units = $chapters->flatMap(fn (Model $chapter): Collection => $chapter->getRelationValue('units') ?? collect());

        $freeUnit = $this->findFreeUnit($units);
        if ($freeUnit instanceof Model) {
            return $freeUnit;
        }

        $incompleteUnit = $this->findFirstIncompleteUnit($units, $histories);
        if ($incompleteUnit instanceof Model && $this->accessResolver->canAccessUnit($user, $course, $incompleteUnit)) {
            $this->firstOrCreateCourseHistory($incompleteUnit, $user);

            return $incompleteUnit;
        }

        $nextUnit = $this->findNextUnwatchedUnit($units, $histories);
        if ($nextUnit instanceof Model && $this->accessResolver->canAccessUnit($user, $course, $nextUnit)) {
            $this->firstOrCreateCourseHistory($nextUnit, $user);

            return $nextUnit;
        }

        $firstUnit = $units->first();

        return $firstUnit instanceof Model ? $firstUnit : null;
    }

    /**
     * @param  Collection<int, Model>  $units
     */
    protected function findFreeUnit(Collection $units): ?Model
    {
        return $units->first(fn (Model $unit): bool => (bool) (data_get($unit, 'isFree') ?? data_get($unit, 'is_free', false)));
    }

    /**
     * @param  Collection<int, Model>  $units
     * @param  Collection<int, Model>  $histories
     */
    protected function findNextUnwatchedUnit(Collection $units, Collection $histories): ?Model
    {
        $watchedUnitIds = $histories->pluck('unit_id')->all();

        return $units->first(fn (Model $unit): bool => ! in_array($unit->getKey(), $watchedUnitIds, true));
    }

    /**
     * @param  Collection<int, Model>  $units
     * @param  Collection<int, Model>  $histories
     */
    protected function findFirstIncompleteUnit(Collection $units, Collection $histories): ?Model
    {
        foreach ($units as $unit) {
            $history = $histories->firstWhere('unit_id', $unit->getKey());

            if (! $history || ! (bool) data_get($history, 'completed', false)) {
                return $unit;
            }
        }

        return null;
    }

    protected function firstOrCreateCourseHistory(Model $unit, ?Authenticatable $user): void
    {
        if (! $user) {
            return;
        }

        $historyModel = config('course-core.models.history');

        if (! is_string($historyModel) || ! class_exists($historyModel)) {
            throw CourseConfigurationException::missingModel('history');
        }

        $historyModel::query()->firstOrCreate(
            [
                'user_id'    => $user->getAuthIdentifier(),
                'course_id'  => data_get($unit, 'course_id'),
                'chapter_id' => data_get($unit, 'parent_id'),
                'unit_id'    => $unit->getKey(),
            ],
            [
                'progress'        => 0,
                'last_watched_at' => now(),
                'completed'       => false,
            ],
        );
    }
}
