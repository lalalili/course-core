<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Lalalili\CourseCore\Contracts\CourseSearchContract;
use Lalalili\CourseCore\Exceptions\CourseConfigurationException;
use Lalalili\CourseCore\Services\CourseFrontendService;
use Lalalili\CourseCore\Support\NullCourseSearch;

// ---------------------------------------------------------------------------
// Inline model helpers
// ---------------------------------------------------------------------------

function frontendCourseModel(): string
{
    return new class extends Model
    {
        protected $table = 'courses';

        protected $guarded = [];

        public $timestamps = false;

        public function scopeValid(Builder $query): Builder
        {
            return $query->where('status', '>=', 1);
        }

        public function category(): BelongsTo
        {
            return $this->belongsTo(frontendCategoryModel(), 'course_category_id');
        }

        public function teacher(): BelongsTo
        {
            return $this->belongsTo(frontendCategoryModel(), 'course_category_id');
        }

        public function product(): BelongsTo
        {
            return $this->belongsTo(frontendCategoryModel(), 'course_category_id');
        }

        public function media(): HasMany
        {
            return $this->hasMany(frontendCategoryModel(), 'id', 'course_category_id');
        }
    }::class;
}

function frontendCategoryModel(): string
{
    return new class extends Model
    {
        protected $table = 'course_categories';

        protected $guarded = [];

        public $timestamps = false;

        public function scopeValid(Builder $query): Builder
        {
            return $query->where('is_active', 1);
        }

        public function courses(): HasMany
        {
            return $this->hasMany(frontendCourseModel(), 'course_category_id');
        }
    }::class;
}

// ---------------------------------------------------------------------------
// Schema setup
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    Schema::dropIfExists('courses');
    Schema::dropIfExists('course_categories');

    Schema::create('course_categories', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->tinyInteger('is_active')->default(1);
        $table->unsignedInteger('sort')->default(0);
    });

    Schema::create('courses', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('course_category_id')->nullable();
        $table->string('title');
        $table->tinyInteger('status')->default(1);
        $table->timestamp('created_at')->nullable();
    });

    config()->set('course-core.models.course', frontendCourseModel());
    config()->set('course-core.models.category', frontendCategoryModel());
});

// ---------------------------------------------------------------------------
// NullCourseSearch
// ---------------------------------------------------------------------------

it('NullCourseSearch returns empty paginator', function (): void {
    $null = new NullCourseSearch;
    $result = $null->searchCourses('php');

    expect($result['keyword'])->toBe('php')
        ->and($result['courses'])->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($result['courses']->total())->toBe(0);
});

// ---------------------------------------------------------------------------
// Container binding
// ---------------------------------------------------------------------------

it('resolves NullCourseSearch when search config is null', function (): void {
    config()->set('course-core.search', null);

    $resolved = app(CourseSearchContract::class);

    expect($resolved)->toBeInstanceOf(NullCourseSearch::class);
});

it('resolves a custom search class when search config is set', function (): void {
    $customSearch = new class implements CourseSearchContract
    {
        public function searchCourses(string $keyword): array
        {
            return ['courses' => new LengthAwarePaginator([], 0, 15), 'keyword' => 'custom'];
        }
    };

    config()->set('course-core.search', $customSearch::class);
    app()->bind($customSearch::class, fn () => $customSearch);

    $resolved = app(CourseSearchContract::class);

    expect($resolved)->toBeInstanceOf($customSearch::class);
});

// ---------------------------------------------------------------------------
// CourseFrontendService::listingData
// ---------------------------------------------------------------------------

it('throws when course model config is missing', function (): void {
    config()->set('course-core.models.course', null);

    $service = new CourseFrontendService(new NullCourseSearch);

    expect(fn () => $service->listingData())->toThrow(CourseConfigurationException::class);
});

it('throws when category model config is missing', function (): void {
    config()->set('course-core.models.category', null);

    $service = new CourseFrontendService(new NullCourseSearch);

    expect(fn () => $service->listingData())->toThrow(CourseConfigurationException::class);
});

it('returns paginated valid courses and all valid categories', function (): void {
    $catClass = frontendCategoryModel();
    $courseClass = frontendCourseModel();

    $catClass::create(['name' => 'PHP', 'is_active' => 1, 'sort' => 1]);
    $catClass::create(['name' => 'Hidden', 'is_active' => 0, 'sort' => 2]);

    $courseClass::create(['title' => 'Laravel', 'status' => 1]);
    $courseClass::create(['title' => 'Draft', 'status' => 0]);

    $service = new CourseFrontendService(new NullCourseSearch);
    $result = $service->listingData(perPage: 10);

    expect($result['courses']->total())->toBe(1)
        ->and($result['courses']->items()[0]->title)->toBe('Laravel')
        ->and($result['categories'])->toHaveCount(1)
        ->and($result['categories']->first()->name)->toBe('PHP');
});

it('paginates courses with the given perPage', function (): void {
    $courseClass = frontendCourseModel();

    foreach (range(1, 5) as $i) {
        $courseClass::create(['title' => "Course {$i}", 'status' => 1]);
    }

    $service = new CourseFrontendService(new NullCourseSearch);
    $result = $service->listingData(perPage: 3);

    expect($result['courses']->total())->toBe(5)
        ->and($result['courses']->perPage())->toBe(3)
        ->and($result['courses']->count())->toBe(3);
});

// ---------------------------------------------------------------------------
// CourseFrontendService::categoryData
// ---------------------------------------------------------------------------

it('returns category, filtered courses, and menuBar', function (): void {
    $catClass = frontendCategoryModel();
    $courseClass = frontendCourseModel();

    $php = $catClass::create(['name' => 'PHP', 'is_active' => 1, 'sort' => 1]);
    $vue = $catClass::create(['name' => 'Vue', 'is_active' => 1, 'sort' => 2]);
    $empty = $catClass::create(['name' => 'Empty', 'is_active' => 1, 'sort' => 3]);

    $courseClass::create(['title' => 'Laravel', 'status' => 1, 'course_category_id' => $php->id]);
    $courseClass::create(['title' => 'Nuxt', 'status' => 1, 'course_category_id' => $vue->id]);
    $courseClass::create(['title' => 'Draft', 'status' => 0, 'course_category_id' => $php->id]);

    $service = new CourseFrontendService(new NullCourseSearch);
    $result = $service->categoryData($php->id);

    expect($result['category']->name)->toBe('PHP')
        ->and($result['courses']->total())->toBe(1)
        ->and($result['courses']->items()[0]->title)->toBe('Laravel');

    $menuIds = $result['menuBarCategories']->pluck('id')->sort()->values()->all();
    expect($menuIds)->toContain($php->id)
        ->and($menuIds)->toContain($vue->id)
        ->and($menuIds)->not->toContain($empty->id);
});

// ---------------------------------------------------------------------------
// CourseFrontendService::searchData
// ---------------------------------------------------------------------------

it('delegates searchData to CourseSearchContract', function (): void {
    $stub = new class implements CourseSearchContract
    {
        public string $capturedKeyword = '';

        public function searchCourses(string $keyword): array
        {
            $this->capturedKeyword = $keyword;

            return ['courses' => new LengthAwarePaginator([], 0, 15), 'keyword' => $keyword];
        }
    };

    $service = new CourseFrontendService($stub);
    $result = $service->searchData('laravel');

    expect($stub->capturedKeyword)->toBe('laravel')
        ->and($result['keyword'])->toBe('laravel');
});
