<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Lalalili\CourseCore\Contracts\CourseAccessResolver;
use Lalalili\CourseCore\Data\CourseUnitVideoPayload;
use Lalalili\CourseCore\Services\CourseFrontendService;
use Lalalili\CourseCore\Services\CoursePlaybackService;
use Lalalili\CourseCore\Services\CourseUnitVideoResolver;
use Lalalili\CourseCore\Support\NullCourseAccessResolver;
use Lalalili\CourseCore\Support\NullCourseSearch;

// ---------------------------------------------------------------------------
// Inline models (prefixed tables to avoid cross-test pollution)
// ---------------------------------------------------------------------------

function classroomCourseModel(): string
{
    return new class extends Model
    {
        protected $table = 'cl_courses';

        protected $guarded = [];

        public $timestamps = false;

        public function scopeValid(Builder $query): Builder
        {
            return $query->where('status', 1);
        }

        public function chapters(): HasMany
        {
            return $this->hasMany(classroomChapterModel(), 'course_id')
                ->where('parent_id', null);
        }

        public function product(): BelongsTo
        {
            return $this->belongsTo(classroomProductModel(), 'product_id');
        }

        public function detail(): BelongsTo
        {
            return $this->belongsTo(classroomDetailModel(), 'detail_id');
        }

        public function ratings(): HasMany
        {
            return $this->hasMany(classroomRatingModel(), 'course_id');
        }
    }::class;
}

function classroomChapterModel(): string
{
    return new class extends Model
    {
        protected $table = 'cl_chapters';

        protected $guarded = [];

        public $timestamps = false;

        public function units(): HasMany
        {
            return $this->hasMany(classroomUnitModel(), 'parent_id');
        }
    }::class;
}

function classroomUnitModel(): string
{
    return new class extends Model
    {
        protected $table = 'cl_chapters';

        protected $guarded = [];

        public $timestamps = false;

        public function video(): BelongsTo
        {
            return $this->belongsTo(classroomVideoModel(), 'video_id');
        }
    }::class;
}

function classroomVideoModel(): string
{
    return new class extends Model
    {
        protected $table = 'cl_videos';

        protected $guarded = [];

        public $timestamps = false;
    }::class;
}

function classroomHistoryModel(): string
{
    return new class extends Model
    {
        protected $table = 'cl_histories';

        protected $guarded = [];

        public $timestamps = false;
    }::class;
}

function classroomRatingModel(): string
{
    return new class extends Model
    {
        protected $table = 'cl_ratings';

        protected $guarded = [];

        public $timestamps = false;
    }::class;
}

function classroomProductModel(): string
{
    return new class extends Model
    {
        protected $table = 'cl_products';

        protected $guarded = [];

        public $timestamps = false;
    }::class;
}

function classroomDetailModel(): string
{
    return new class extends Model
    {
        protected $table = 'cl_details';

        protected $guarded = [];

        public $timestamps = false;
    }::class;
}

// ---------------------------------------------------------------------------
// Stub helpers
// ---------------------------------------------------------------------------

/** Stub CourseUnitVideoResolver — returns a fixed videoId for any non-null unit */
function classroomVideoResolver(): CourseUnitVideoResolver
{
    return new class extends CourseUnitVideoResolver
    {
        public function __construct() {}

        public function resolve(?Model $unit): CourseUnitVideoPayload
        {
            if (! $unit) {
                return new CourseUnitVideoPayload(null, null, null);
            }

            return new CourseUnitVideoPayload(
                videoId: 'test-video-'.$unit->getKey(),
                videoProvider: 'vimeo',
                embedUrl: 'https://player.vimeo.com/video/test',
            );
        }
    };
}

/** Stub CoursePlaybackService — always returns the first unit from chapters */
function classroomPlaybackService(?CourseAccessResolver $access = null): CoursePlaybackService
{
    return new class($access ?? new NullCourseAccessResolver) extends CoursePlaybackService
    {
        public function initializeUnit(Model $course, Collection $chapters, Collection $histories, ?Authenticatable $user = null): ?Model
        {
            return $chapters->flatMap(fn ($ch) => $ch->getRelationValue('units') ?? collect())->first();
        }
    };
}

/** Stub user */
function classroomUser(int $id = 1): Authenticatable
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

/** Build a CourseFrontendService wired with classroom stubs */
function classroomService(?CourseAccessResolver $access = null): CourseFrontendService
{
    return new CourseFrontendService(
        search: new NullCourseSearch,
        accessResolver: $access ?? new NullCourseAccessResolver,
        videoResolver: classroomVideoResolver(),
        playbackService: classroomPlaybackService($access),
    );
}

// ---------------------------------------------------------------------------
// Schema setup
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    foreach (['cl_courses', 'cl_chapters', 'cl_histories', 'cl_ratings', 'cl_products', 'cl_details', 'cl_videos'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('cl_products', fn (Blueprint $t) => $t->id());
    Schema::create('cl_details', function (Blueprint $t): void {
        $t->id();
        $t->text('description')->nullable();
    });
    Schema::create('cl_videos', function (Blueprint $t): void {
        $t->id();
        $t->string('provider')->nullable();
        $t->string('provider_video_id')->nullable();
    });
    Schema::create('cl_courses', function (Blueprint $t): void {
        $t->id();
        $t->tinyInteger('status')->default(1);
        $t->unsignedBigInteger('product_id')->nullable();
        $t->unsignedBigInteger('detail_id')->nullable();
    });
    Schema::create('cl_chapters', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('course_id');
        $t->unsignedBigInteger('parent_id')->nullable();
        $t->unsignedBigInteger('video_id')->nullable();
        $t->integer('sort')->default(0);
        $t->integer('duration')->default(0);
        $t->tinyInteger('is_free')->default(0);
    });
    Schema::create('cl_histories', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->unsignedBigInteger('course_id');
        $t->unsignedBigInteger('unit_id');
        $t->boolean('completed')->default(false);
        $t->integer('progress')->default(0);
    });
    Schema::create('cl_ratings', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('course_id');
        $t->unsignedBigInteger('user_id');
        $t->string('title')->default('');
        $t->text('comment')->default('');
        $t->integer('score')->default(0);
        $t->timestamp('created_at')->nullable();
    });

    config()->set('course-core.models.course', classroomCourseModel());
    config()->set('course-core.models.history', classroomHistoryModel());
});

// ---------------------------------------------------------------------------
// Helper: create a minimal course with one chapter + one unit
// ---------------------------------------------------------------------------

function classroomSeedCourse(array $chapterAttrs = [], array $unitAttrs = []): Model
{
    $product = classroomProductModel()::create([]);
    $course = classroomCourseModel()::create(['status' => 1, 'product_id' => $product->id]);
    $chapter = classroomChapterModel()::create(['course_id' => $course->id, 'sort' => 1, ...$chapterAttrs]);
    classroomUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 1, ...$unitAttrs]);

    return $course;
}

// ---------------------------------------------------------------------------
// classroomData — structure
// ---------------------------------------------------------------------------

it('classroomData returns expected keys', function (): void {
    $course = classroomSeedCourse();
    $data = classroomService()->classroomData($course);

    expect($data)->toHaveKeys([
        'chapters', 'courseHistory', 'currentUnit',
        'chapterId', 'unitId', 'video', 'progress', 'purchased', 'myRating',
    ]);
});

it('courseHistory is empty collection for null user', function (): void {
    $course = classroomSeedCourse();
    $data = classroomService()->classroomData($course, null);

    expect($data['courseHistory'])->toHaveCount(0)
        ->and($data['purchased'])->toBeFalse();
});

it('courseHistory is loaded for authenticated user', function (): void {
    $course = classroomSeedCourse();
    $unit = classroomUnitModel()::where('course_id', $course->id)->first();
    $user = classroomUser(42);

    classroomHistoryModel()::create([
        'user_id' => 42,
        'course_id' => $course->id,
        'unit_id' => $unit->id,
        'completed' => false,
    ]);

    $data = classroomService()->classroomData($course, $user);

    expect($data['courseHistory'])->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// classroomData — currentUnit + video
// ---------------------------------------------------------------------------

it('currentUnit and video populated when chapters exist', function (): void {
    $course = classroomSeedCourse();
    $unit = classroomUnitModel()::where('course_id', $course->id)->whereNotNull('parent_id')->first();

    $data = classroomService()->classroomData($course);

    expect($data['currentUnit'])->not->toBeNull()
        ->and($data['unitId'])->toBe($unit->id)
        ->and($data['video']['videoId'])->toBe('test-video-'.$unit->id);
});

it('currentUnit is null and video is empty payload when no chapters', function (): void {
    $course = classroomCourseModel()::create(['status' => 1]);

    $data = classroomService()->classroomData($course);

    expect($data['currentUnit'])->toBeNull()
        ->and($data['video']['videoId'])->toBeNull();
});

// ---------------------------------------------------------------------------
// classroomData — progress
// ---------------------------------------------------------------------------

it('progress is 0 when units have no duration', function (): void {
    $course = classroomSeedCourse(unitAttrs: ['duration' => 0]);
    $data = classroomService()->classroomData($course);

    expect($data['progress'])->toBe(0);
});

it('progress is 0 for null user even if units have duration', function (): void {
    $course = classroomSeedCourse(unitAttrs: ['duration' => 100]);
    $data = classroomService()->classroomData($course, null);

    expect($data['progress'])->toBe(0);
});

it('progress reflects completed unit duration ratio', function (): void {
    $course = classroomSeedCourse();
    $chapter = classroomChapterModel()::where('course_id', $course->id)->whereNull('parent_id')->first();

    $unit1 = classroomUnitModel()::where('course_id', $course->id)->whereNotNull('parent_id')->first();
    $unit1->update(['duration' => 60]);
    $unit2 = classroomUnitModel()::create(['course_id' => $course->id, 'parent_id' => $chapter->id, 'sort' => 2, 'duration' => 40]);

    $user = classroomUser(7);
    classroomHistoryModel()::create([
        'user_id' => 7,
        'course_id' => $course->id,
        'unit_id' => $unit1->id,
        'completed' => true,
    ]);

    $data = classroomService()->classroomData($course, $user);

    expect($data['progress'])->toBe(60); // 60 / (60+40) * 100
});

// ---------------------------------------------------------------------------
// classroomData — purchased
// ---------------------------------------------------------------------------

it('purchased is false when access resolver denies', function (): void {
    $course = classroomSeedCourse();
    $user = classroomUser();
    $data = classroomService()->classroomData($course, $user);

    expect($data['purchased'])->toBeFalse();
});

it('purchased is true when access resolver grants', function (): void {
    $grantingAccess = new class implements CourseAccessResolver
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

    $course = classroomSeedCourse();
    $user = classroomUser();
    $data = classroomService($grantingAccess)->classroomData($course, $user);

    expect($data['purchased'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// classroomData — myRating
// ---------------------------------------------------------------------------

it('myRating is null for null user', function (): void {
    $course = classroomSeedCourse();
    $data = classroomService()->classroomData($course, null);

    expect($data['myRating'])->toBeNull();
});

it('myRating is null when user has no rating', function (): void {
    $course = classroomSeedCourse();
    $data = classroomService()->classroomData($course, classroomUser(99));

    expect($data['myRating'])->toBeNull();
});

it('myRating contains title, comment, score for the authenticated user', function (): void {
    $course = classroomSeedCourse();
    classroomRatingModel()::create([
        'course_id' => $course->id,
        'user_id' => 5,
        'title' => '很棒',
        'comment' => '推薦',
        'score' => 5,
    ]);

    $data = classroomService()->classroomData($course, classroomUser(5));

    expect($data['myRating'])->toBe(['title' => '很棒', 'comment' => '推薦', 'score' => 5]);
});
