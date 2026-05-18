<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lalalili\CourseCore\Contracts\CourseAccessResolver;
use Lalalili\CourseCore\Data\CourseUnitVideoPayload;
use Lalalili\CourseCore\Services\CourseFrontendService;
use Lalalili\CourseCore\Services\CourseProgressService;
use Lalalili\CourseCore\Services\CourseUnitVideoResolver;
use Lalalili\CourseCore\Support\NullCourseAccessResolver;
use Lalalili\CourseCore\Support\NullCourseSearch;

// ---------------------------------------------------------------------------
// Inline models (pg_ prefix to avoid cross-test pollution)
// ---------------------------------------------------------------------------

function pgCourseModel(): string
{
    return new class extends Model
    {
        protected $table = 'pg_courses';

        protected $guarded = [];

        public $timestamps = false;

        public function scopeValid(Builder $query): Builder
        {
            return $query->where('status', 1);
        }

        public function chapters(): HasMany
        {
            return $this->hasMany(pgChapterModel(), 'course_id')
                ->whereNull('parent_id');
        }
    }::class;
}

function pgChapterModel(): string
{
    return new class extends Model
    {
        protected $table = 'pg_chapters';

        protected $guarded = [];

        public $timestamps = false;

        public function units(): HasMany
        {
            return $this->hasMany(pgUnitModel(), 'parent_id');
        }
    }::class;
}

function pgUnitModel(): string
{
    return new class extends Model
    {
        protected $table = 'pg_chapters';

        protected $guarded = [];

        public $timestamps = false;

        public function video(): BelongsTo
        {
            return $this->belongsTo(get_class($this), 'video_id');
        }
    }::class;
}

function pgHistoryModel(): string
{
    return new class extends Model
    {
        protected $table = 'pg_histories';

        protected $guarded = [];

        public $timestamps = false;
    }::class;
}

// ---------------------------------------------------------------------------
// Stub helpers
// ---------------------------------------------------------------------------

function pgUser(int $id = 1): Authenticatable
{
    return new class($id) implements Authenticatable
    {
        public function __construct(protected int $id) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return $this->id;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };
}

function pgAllowAccess(): CourseAccessResolver
{
    return new class implements CourseAccessResolver
    {
        public function canViewCourse(?Authenticatable $user, Model $course): bool
        {
            return true;
        }

        public function hasPurchasedCourse(?Authenticatable $user, Model $course): bool
        {
            return true;
        }

        public function canAccessUnit(?Authenticatable $user, Model $course, Model $unit): bool
        {
            return true;
        }
    };
}

function pgDenyAccess(): CourseAccessResolver
{
    return new class implements CourseAccessResolver
    {
        public function canViewCourse(?Authenticatable $user, Model $course): bool
        {
            return false;
        }

        public function hasPurchasedCourse(?Authenticatable $user, Model $course): bool
        {
            return false;
        }

        public function canAccessUnit(?Authenticatable $user, Model $course, Model $unit): bool
        {
            return false;
        }
    };
}

function pgVideoResolver(): CourseUnitVideoResolver
{
    return new class extends CourseUnitVideoResolver
    {
        public function __construct() {}

        public function resolve(?Model $unit): CourseUnitVideoPayload
        {
            return new CourseUnitVideoPayload(
                videoId: $unit ? 'v'.$unit->getKey() : null,
                videoProvider: $unit ? 'vimeo' : null,
                embedUrl: null,
            );
        }
    };
}

function pgService(?CourseAccessResolver $access = null): CourseFrontendService
{
    return new CourseFrontendService(
        search: new NullCourseSearch,
        accessResolver: $access ?? new NullCourseAccessResolver,
        videoResolver: pgVideoResolver(),
    );
}

// ---------------------------------------------------------------------------
// Schema
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    Schema::dropIfExists('pg_histories');
    Schema::dropIfExists('pg_chapters');
    Schema::dropIfExists('pg_courses');

    Schema::create('pg_courses', function (Blueprint $t): void {
        $t->id();
        $t->tinyInteger('status')->default(1);
    });
    Schema::create('pg_chapters', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('course_id');
        $t->unsignedBigInteger('parent_id')->nullable();
        $t->integer('sort')->default(0);
        $t->integer('duration')->default(0);
    });
    Schema::create('pg_histories', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->unsignedBigInteger('course_id');
        $t->unsignedBigInteger('chapter_id');
        $t->unsignedBigInteger('unit_id');
        $t->float('progress')->default(0);
        $t->boolean('completed')->default(false);
        $t->timestamp('last_watched_at')->nullable();
    });

    config()->set('course-core.models.history', pgHistoryModel());
    config()->set('course-core.models.chapter', pgChapterModel());
    config()->set('course-core.models.course', pgCourseModel());
});

// ---------------------------------------------------------------------------
// CourseProgressService — sync
// ---------------------------------------------------------------------------

it('sync creates a new history record', function (): void {
    $service = new CourseProgressService;
    $service->sync(
        userId: 1, courseId: 10, chapterId: 2, unitId: 3,
        progress: 50.0, lastWatchedAt: null, completed: false,
    );

    $record = pgHistoryModel()::first();
    expect($record)->not->toBeNull()
        ->and((float) $record->progress)->toBe(50.0)
        ->and((bool) $record->completed)->toBeFalse();
});

it('sync updates an existing record', function (): void {
    $service = new CourseProgressService;
    $service->sync(userId: 1, courseId: 10, chapterId: 2, unitId: 3, progress: 30.0, lastWatchedAt: null, completed: false);
    $service->sync(userId: 1, courseId: 10, chapterId: 2, unitId: 3, progress: 80.0, lastWatchedAt: null, completed: true);

    expect(pgHistoryModel()::count())->toBe(1)
        ->and((float) pgHistoryModel()::first()->progress)->toBe(80.0);
});

it('sync does not regress completed status to false', function (): void {
    $service = new CourseProgressService;
    $service->sync(userId: 1, courseId: 10, chapterId: 2, unitId: 3, progress: 100.0, lastWatchedAt: null, completed: true);
    $service->sync(userId: 1, courseId: 10, chapterId: 2, unitId: 3, progress: 50.0, lastWatchedAt: null, completed: false);

    expect((bool) pgHistoryModel()::first()->completed)->toBeTrue();
});

// ---------------------------------------------------------------------------
// CourseProgressService — fetch
// ---------------------------------------------------------------------------

it('fetch returns null when no record exists', function (): void {
    $service = new CourseProgressService;
    expect($service->fetch(1, 10, 2, 3))->toBeNull();
});

it('fetch returns the existing record', function (): void {
    pgHistoryModel()::create([
        'user_id' => 1, 'course_id' => 10, 'chapter_id' => 2, 'unit_id' => 3,
        'progress' => 42.0, 'completed' => false,
    ]);

    $service = new CourseProgressService;
    $record = $service->fetch(1, 10, 2, 3);

    expect($record)->not->toBeNull()
        ->and((float) data_get($record, 'progress'))->toBe(42.0);
});

// ---------------------------------------------------------------------------
// CourseFrontendService::syncProgress / fetchProgress — unit validation
// ---------------------------------------------------------------------------

it('syncProgress throws ModelNotFoundException for unknown unit', function (): void {
    $course = pgCourseModel()::create([]);
    $user = pgUser();

    $service = pgService(pgAllowAccess());
    expect(fn () => $service->syncProgress($user, $course, 99, 999, 50.0, null, false))
        ->toThrow(ModelNotFoundException::class);
});

it('syncProgress throws AuthorizationException when access denied', function (): void {
    $course = pgCourseModel()::create([]);
    $chapter = pgChapterModel()::create(['course_id' => $course->id]);
    $unit = pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id]);

    $service = pgService(pgDenyAccess());
    $user = pgUser();

    expect(fn () => $service->syncProgress($user, $course, $chapter->id, $unit->id, 50.0, null, false))
        ->toThrow(AuthorizationException::class);
});

it('syncProgress writes a history record when access is granted', function (): void {
    $course = pgCourseModel()::create([]);
    $chapter = pgChapterModel()::create(['course_id' => $course->id]);
    $unit = pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id]);

    $service = pgService(pgAllowAccess());
    $service->syncProgress(pgUser(5), $course, $chapter->id, $unit->id, 75.0, null, false);

    $record = pgHistoryModel()::first();
    expect($record)->not->toBeNull()
        ->and((float) $record->progress)->toBe(75.0)
        ->and((int) $record->user_id)->toBe(5);
});

it('fetchProgress throws AuthorizationException when access denied', function (): void {
    $course = pgCourseModel()::create([]);
    $chapter = pgChapterModel()::create(['course_id' => $course->id]);
    $unit = pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id]);

    $service = pgService(pgDenyAccess());

    expect(fn () => $service->fetchProgress(pgUser(), $course, $chapter->id, $unit->id))
        ->toThrow(AuthorizationException::class);
});

it('fetchProgress returns null when no record exists yet', function (): void {
    $course = pgCourseModel()::create([]);
    $chapter = pgChapterModel()::create(['course_id' => $course->id]);
    $unit = pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id]);

    $record = pgService(pgAllowAccess())->fetchProgress(pgUser(), $course, $chapter->id, $unit->id);

    expect($record)->toBeNull();
});

// ---------------------------------------------------------------------------
// CourseFrontendService::findClosestUnit
// ---------------------------------------------------------------------------

it('findClosestUnit returns all-null when activeUnitId not found', function (): void {
    $course = pgCourseModel()::create([]);
    $result = pgService()->findClosestUnit($course, 9999);

    expect($result)->toBe(['previousUnit' => null, 'currentUnit' => null, 'nextUnit' => null]);
});

it('findClosestUnit returns current with null prev/next for single unit', function (): void {
    $course = pgCourseModel()::create([]);
    $chapter = pgChapterModel()::create(['course_id' => $course->id, 'sort' => 1]);
    $unit = pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 1]);

    $result = pgService()->findClosestUnit($course, $unit->id);

    expect($result['currentUnit'])->not->toBeNull()
        ->and($result['previousUnit'])->toBeNull()
        ->and($result['nextUnit'])->toBeNull();
});

it('findClosestUnit exposes prev and next when access resolver grants access', function (): void {
    $course = pgCourseModel()::create([]);
    $chapter = pgChapterModel()::create(['course_id' => $course->id, 'sort' => 1]);
    $unit1 = pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 1]);
    $unit2 = pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 2]);
    $unit3 = pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 3]);

    $result = pgService(pgAllowAccess())->findClosestUnit($course, $unit2->id, pgUser());

    expect($result['previousUnit'])->not->toBeNull()
        ->and($result['currentUnit'])->not->toBeNull()
        ->and($result['nextUnit'])->not->toBeNull()
        ->and($result['previousUnit']['videoId'])->toBe('v'.$unit1->id)
        ->and($result['nextUnit']['videoId'])->toBe('v'.$unit3->id);
});

it('findClosestUnit hides prev/next when no user provided', function (): void {
    $course = pgCourseModel()::create([]);
    $chapter = pgChapterModel()::create(['course_id' => $course->id, 'sort' => 1]);
    pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 1]);
    $unit2 = pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 2]);
    pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 3]);

    $result = pgService(pgAllowAccess())->findClosestUnit($course, $unit2->id, user: null);

    expect($result['previousUnit'])->toBeNull()
        ->and($result['nextUnit'])->toBeNull()
        ->and($result['currentUnit'])->not->toBeNull();
});

it('findClosestUnit hides prev/next when access resolver denies', function (): void {
    $course = pgCourseModel()::create([]);
    $chapter = pgChapterModel()::create(['course_id' => $course->id, 'sort' => 1]);
    pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 1]);
    $unit2 = pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 2]);
    pgUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 3]);

    $result = pgService(pgDenyAccess())->findClosestUnit($course, $unit2->id, pgUser());

    expect($result['previousUnit'])->toBeNull()
        ->and($result['nextUnit'])->toBeNull();
});
