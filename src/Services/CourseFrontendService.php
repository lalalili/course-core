<?php

namespace Lalalili\CourseCore\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection as SupportCollection;
use Lalalili\CourseCore\Contracts\CourseAccessResolver;
use Lalalili\CourseCore\Contracts\CourseSearchContract;
use Lalalili\CourseCore\Contracts\RichContentRendererContract;
use Lalalili\CourseCore\Data\CourseUnitVideoPayload;
use Lalalili\CourseCore\Exceptions\CourseConfigurationException;
use Lalalili\CourseCore\Support\NullCourseAccessResolver;
use Lalalili\CourseCore\Support\NullRichContentRenderer;

class CourseFrontendService
{
    public function __construct(
        protected CourseSearchContract $search,
        protected CourseAccessResolver $accessResolver = new NullCourseAccessResolver,
        protected RichContentRendererContract $richContent = new NullRichContentRenderer,
        protected ?CourseUnitVideoResolver $videoResolver = null,
        protected ?CoursePlaybackService $playbackService = null,
        protected ?CourseProgressService $progressService = null,
    ) {}

    protected function getVideoResolver(): CourseUnitVideoResolver
    {
        return $this->videoResolver ??= app(CourseUnitVideoResolver::class);
    }

    protected function getPlaybackService(): CoursePlaybackService
    {
        return $this->playbackService ??= app(CoursePlaybackService::class);
    }

    protected function getProgressService(): CourseProgressService
    {
        return $this->progressService ??= app(CourseProgressService::class);
    }

    /** @return class-string<Model> */
    protected function courseModelClass(): string
    {
        $model = config('course-core.models.course');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw CourseConfigurationException::missingModel('course');
        }

        return $model;
    }

    /** @return class-string<Model> */
    protected function categoryModelClass(): string
    {
        $model = config('course-core.models.category');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw CourseConfigurationException::missingModel('category');
        }

        return $model;
    }

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
     * Data for the course listing page.
     *
     * Returns all valid (published) courses with teacher/product/category/media
     * eager-loaded, plus every valid category. Accessor appending (coverUrl,
     * likesCount, etc.) is host-specific and must be done in the controller.
     *
     * @return array{courses: LengthAwarePaginator<int, Model>, categories: Collection<int, Model>}
     */
    public function listingData(int $perPage = 12): array
    {
        $courseClass = $this->courseModelClass();
        $categoryClass = $this->categoryModelClass();

        $courses = $this->applyValidScope($courseClass::query())
            ->with(['teacher', 'product', 'category', 'media'])
            ->paginate($perPage);

        $categories = $this->applyValidScope($categoryClass::query())
            ->get();

        return compact('courses', 'categories');
    }

    /**
     * Data for a course category filter page.
     *
     * Returns the matched category, paginated courses in that category, and the
     * sidebar menu bar (categories that have at least one valid course, plus the
     * current category regardless of course count).
     *
     * @return array{category: Model, courses: LengthAwarePaginator<int, Model>, menuBarCategories: Collection<int, Model>}
     */
    public function categoryData(int $categoryId, int $perPage = 8): array
    {
        $courseClass = $this->courseModelClass();
        $categoryClass = $this->categoryModelClass();

        $category = $categoryClass::query()->findOrFail($categoryId);

        $courses = $this->applyValidScope($courseClass::query())
            ->whereHas('category', fn ($q) => $q->where('id', $categoryId))
            ->with(['teacher', 'product', 'category', 'media'])
            ->latest()
            ->paginate($perPage);

        $menuBarCategories = $categoryClass::query()
            ->where(function ($q) use ($categoryId): void {
                $q->whereHas('courses', fn ($inner) => $this->applyValidScope($inner))
                    ->orWhere('id', $categoryId);
            })
            ->orderBy('sort')
            ->get();

        return compact('category', 'courses', 'menuBarCategories');
    }

    /**
     * Data for the course search results page.
     *
     * Delegates to the bound CourseSearchContract. New projects without a search
     * engine get NullCourseSearch which returns an empty paginator.
     *
     * @return array{courses: LengthAwarePaginator<int, mixed>, keyword: string}
     */
    public function searchData(string $keyword): array
    {
        return $this->search->searchCourses($keyword);
    }

    /**
     * Data for the course detail (show) page.
     *
     * Loads all relations, renders rich-text fields via RichContentRendererContract,
     * and sets a `purchased` attribute on the course. Returns null when the course
     * is not found (host controller should abort(404) in that case).
     *
     * Host responsibilities (not done here): accessor appending, cartContent,
     * createVisitLog(), staff bypass of the valid() scope.
     */
    public function showData(string $courseUlid, ?Authenticatable $user = null): ?Model
    {
        $courseClass = $this->courseModelClass();

        $course = $this->applyValidScope($courseClass::query())
            ->with('product')
            ->where('ulid', $courseUlid)
            ->first();

        if (! $course || ! $course->getRelationValue('product')) {
            return null;
        }

        $course->load([
            'teacher',
            'detail',
            'chapters',
            'chapters.units',
            'ratings' => fn ($q) => $q->orderBy('created_at', 'desc'),
            'ratings.user',
            'tags',
        ]);

        if ($teacher = $course->getRelationValue('teacher')) {
            $teacher->profile = $this->richContent->renderContent(
                data_get($teacher, 'profile')
            );
        }

        $detail = $course->getRelationValue('detail');
        if ($detail) {
            $detail->description = $this->richContent->renderContent(
                data_get($detail, 'description')
            );
            $detail->product_desc = $this->richContent->renderContent(
                data_get($detail, 'product_desc')
            );
            $detail->faq = $this->richContent->renderFaqs(
                data_get($detail, 'faq')
            );
        }

        $course->setAttribute(
            'purchased',
            $user && $course->getRelationValue('product')
                ? $this->accessResolver->hasPurchasedCourse($user, $course)
                : false,
        );

        return $course;
    }

    /**
     * Data for the course classroom (playback) page.
     *
     * Loads chapters with units and video, determines the current unit via
     * CoursePlaybackService, resolves the video payload, calculates duration-based
     * progress, and derives the purchased flag and user's own rating.
     *
     * Host responsibilities (not done here): access-control aborts (no currentUnit /
     * canAccessUnit), cartContent, materials from Spatie media, Inertia render.
     *
     * @return array{
     *   chapters: Collection<int, Model>,
     *   courseHistory: SupportCollection<int, Model>,
     *   currentUnit: ?Model,
     *   chapterId: ?int,
     *   unitId: ?int,
     *   video: array<string, mixed>,
     *   progress: int,
     *   purchased: bool,
     *   myRating: ?array{title: string, comment: string, score: int},
     * }
     */
    public function classroomData(Model $course, ?Authenticatable $user = null): array
    {
        $course->loadMissing([
            'detail',
            'chapters.units.video',
            'ratings' => fn ($q) => $q->orderBy('created_at', 'desc'),
            'product',
        ]);

        $chapters = $this->relationCollection($course, 'chapters')
            ->sortBy(fn (Model $chapter): mixed => data_get($chapter, 'sort'))
            ->values();

        $historyClass = $this->historyModelClass();
        $courseHistory = $user
            ? $historyClass::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where('course_id', $course->getKey())
                ->get()
            : collect();

        $currentUnit = $this->getPlaybackService()
            ->initializeUnit($course, $chapters, $courseHistory, $user);

        $video = $currentUnit
            ? $this->getVideoResolver()->resolve($currentUnit)->toFrontendArray()
            : (new CourseUnitVideoPayload(null, null, null))->toFrontendArray();

        $detail = $course->getRelationValue('detail');
        if ($detail) {
            $detail->description = $this->richContent->renderContent(
                data_get($detail, 'description')
            );
        }

        $units = $this->unitsFromChapters($chapters);
        $progress = $this->calculateProgress($units, $courseHistory);

        $chapters->each(function ($chapter): void {
            $chapterUnits = $this->relationCollection($chapter, 'units');
            $chapter->setAttribute('isFree', $chapterUnits->contains(
                fn ($unit) => (bool) (data_get($unit, 'isFree') ?? data_get($unit, 'is_free', false))
            ));
        });

        $product = $course->getRelationValue('product');
        $purchased = $user && $product
            ? $this->accessResolver->hasPurchasedCourse($user, $course)
            : false;

        $myRating = null;
        if ($user) {
            $rating = $course->getRelationValue('ratings')
                ?->firstWhere('user_id', $user->getAuthIdentifier());
            if ($rating) {
                $myRating = [
                    'title' => (string) data_get($rating, 'title', ''),
                    'comment' => (string) data_get($rating, 'comment', ''),
                    'score' => (int) data_get($rating, 'score', 0),
                ];
            }
        }

        return [
            'chapters' => $chapters,
            'courseHistory' => $courseHistory,
            'currentUnit' => $currentUnit,
            'chapterId' => $currentUnit ? (int) data_get($currentUnit, 'parent_id') : null,
            'unitId' => $currentUnit ? (int) $currentUnit->getKey() : null,
            'video' => $video,
            'progress' => $progress,
            'purchased' => $purchased,
            'myRating' => $myRating,
        ];
    }

    /**
     * Sync a unit's watch progress for the authenticated user.
     *
     * Verifies the unit belongs to the given course/chapter and that the user
     * has access (via CourseAccessResolver::canAccessUnit). Throws
     * AuthorizationException if access is denied, ModelNotFoundException if the
     * unit is not found.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function syncProgress(
        Authenticatable $user,
        Model $course,
        int $chapterId,
        int $unitId,
        float $progress,
        ?string $lastWatchedAt,
        bool $completed,
    ): void {
        $unit = $this->resolveUnit($course, $chapterId, $unitId);

        if (! $this->accessResolver->canAccessUnit($user, $course, $unit)) {
            throw new AuthorizationException('尚未購買該單元');
        }

        $this->getProgressService()->sync(
            userId: (int) $user->getAuthIdentifier(),
            courseId: (int) $course->getKey(),
            chapterId: $chapterId,
            unitId: $unitId,
            progress: $progress,
            lastWatchedAt: $lastWatchedAt,
            completed: $completed,
        );
    }

    /**
     * Fetch the progress record for a specific unit.
     *
     * Verifies ownership and access before returning the record. Returns null
     * if the unit has never been watched.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function fetchProgress(
        Authenticatable $user,
        Model $course,
        int $chapterId,
        int $unitId,
    ): ?Model {
        $unit = $this->resolveUnit($course, $chapterId, $unitId);

        if (! $this->accessResolver->canAccessUnit($user, $course, $unit)) {
            throw new AuthorizationException('尚未購買該單元');
        }

        return $this->getProgressService()->fetch(
            userId: (int) $user->getAuthIdentifier(),
            courseId: (int) $course->getKey(),
            chapterId: $chapterId,
            unitId: $unitId,
        );
    }

    /**
     * Find the previous, current and next units relative to a given activeUnitId.
     *
     * Returns navigation payloads for each position. Previous and next units are
     * only resolved when the user has access; they are null otherwise.
     * Returns all-null when activeUnitId is not found in the course.
     *
     * @return array{previousUnit: ?array<string, mixed>, currentUnit: ?array<string, mixed>, nextUnit: ?array<string, mixed>}
     */
    public function findClosestUnit(Model $course, int $activeUnitId, ?Authenticatable $user = null): array
    {
        $course->loadMissing('chapters.units.video');
        $chapters = $this->relationCollection($course, 'chapters')
            ->sortBy(fn (Model $chapter): mixed => data_get($chapter, 'sort'))
            ->values();

        $units = $this->unitsFromChapters($chapters);
        $currentIndex = $units->search(fn ($unit) => $unit->getKey() === $activeUnitId);

        if (! is_int($currentIndex)) {
            return ['previousUnit' => null, 'currentUnit' => null, 'nextUnit' => null];
        }

        $currentUnit = $units->get($currentIndex);
        if (! $currentUnit instanceof Model) {
            return ['previousUnit' => null, 'currentUnit' => null, 'nextUnit' => null];
        }

        $previousUnit = $currentIndex > 0 ? $units->get($currentIndex - 1) : null;
        $nextUnit = $currentIndex < $units->count() - 1 ? $units->get($currentIndex + 1) : null;
        $resolver = $this->getVideoResolver();

        return [
            'previousUnit' => $previousUnit && $user && $this->accessResolver->canAccessUnit($user, $course, $previousUnit)
                ? $resolver->navigationPayload($previousUnit)
                : null,
            'currentUnit' => $resolver->navigationPayload($currentUnit),
            'nextUnit' => $nextUnit && $user && $this->accessResolver->canAccessUnit($user, $course, $nextUnit)
                ? $resolver->navigationPayload($nextUnit)
                : null,
        ];
    }

    /**
     * @param  SupportCollection<int, Model>  $units
     * @param  SupportCollection<int, Model>  $histories
     */
    private function calculateProgress(SupportCollection $units, SupportCollection $histories): int
    {
        $totalDuration = $units->sum('duration');

        if ($totalDuration <= 0) {
            return 0;
        }

        $completedUnitIds = $histories
            ->where('completed', true)
            ->pluck('unit_id')
            ->all();

        $completedDuration = $units
            ->filter(fn ($unit) => in_array($unit->getKey(), $completedUnitIds, true))
            ->sum('duration');

        return (int) round($completedDuration / $totalDuration * 100);
    }

    /**
     * Resolve a unit model that belongs to the given course and chapter.
     *
     * @throws ModelNotFoundException
     */
    private function resolveUnit(Model $course, int $chapterId, int $unitId): Model
    {
        $chapterClass = config('course-core.models.chapter');

        if (! is_string($chapterClass) || ! is_subclass_of($chapterClass, Model::class)) {
            throw CourseConfigurationException::missingModel('chapter');
        }

        $unit = $chapterClass::query()
            ->whereKey($unitId)
            ->where('course_id', $course->getKey())
            ->where('parent_id', $chapterId)
            ->first();

        if (! $unit instanceof Model) {
            throw new ModelNotFoundException('Unit not found');
        }

        return $unit;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function applyValidScope(Builder $query): Builder
    {
        $model = $query->getModel();

        if (method_exists($model, 'scopeValid')) {
            $model->callNamedScope('valid', [$query]);
        }

        return $query;
    }

    /**
     * @return Collection<int, Model>
     */
    private function relationCollection(Model $model, string $relation): Collection
    {
        $value = $model->getRelationValue($relation);

        if ($value instanceof Collection) {
            return $value;
        }

        return new Collection;
    }

    /**
     * @param  Collection<int, Model>  $chapters
     * @return SupportCollection<int, Model>
     */
    private function unitsFromChapters(Collection $chapters): SupportCollection
    {
        return $chapters
            ->flatMap(fn (Model $chapter): Collection => $this->relationCollection($chapter, 'units'))
            ->values();
    }
}
