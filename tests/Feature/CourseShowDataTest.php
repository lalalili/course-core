<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lalalili\CourseCore\Contracts\CourseAccessResolver;
use Lalalili\CourseCore\Contracts\RichContentRendererContract;
use Lalalili\CourseCore\Services\CourseFrontendService;
use Lalalili\CourseCore\Support\NullCourseAccessResolver;
use Lalalili\CourseCore\Support\NullCourseSearch;
use Lalalili\CourseCore\Support\NullRichContentRenderer;

// ---------------------------------------------------------------------------
// Inline models
// ---------------------------------------------------------------------------

function showCourseModel(): string
{
    return new class extends Model
    {
        protected $table = 'courses';

        protected $guarded = [];

        public $timestamps = false;

        public function scopeValid(Builder $query): Builder
        {
            return $query->where('status', 1);
        }

        public function product(): BelongsTo
        {
            return $this->belongsTo(showProductModel(), 'product_id');
        }

        public function teacher(): BelongsTo
        {
            return $this->belongsTo(showTeacherModel(), 'teacher_id');
        }

        public function detail(): BelongsTo
        {
            return $this->belongsTo(showDetailModel(), 'detail_id');
        }

        public function chapters(): HasMany
        {
            return $this->hasMany(showChapterModel(), 'course_id');
        }

        public function ratings(): HasMany
        {
            return $this->hasMany(showRatingModel(), 'course_id');
        }

        public function tags(): HasMany
        {
            return $this->hasMany(showTagModel(), 'course_id');
        }
    }::class;
}

function showProductModel(): string
{
    return new class extends Model
    {
        protected $table = 'products';

        protected $guarded = [];

        public $timestamps = false;
    }::class;
}

function showTeacherModel(): string
{
    return new class extends Model
    {
        protected $table = 'teachers';

        protected $guarded = [];

        public $timestamps = false;
    }::class;
}

function showDetailModel(): string
{
    return new class extends Model
    {
        protected $table = 'course_details';

        protected $guarded = [];

        public $timestamps = false;
    }::class;
}

function showChapterModel(): string
{
    return new class extends Model
    {
        protected $table = 'chapters';

        protected $guarded = [];

        public $timestamps = false;

        public function units(): HasMany
        {
            return $this->hasMany(get_class($this), 'parent_id');
        }
    }::class;
}

function showRatingModel(): string
{
    return new class extends Model
    {
        protected $table = 'ratings';

        protected $guarded = [];

        public $timestamps = false;

        public function user(): BelongsTo
        {
            return $this->belongsTo(get_class($this), 'user_id');
        }
    }::class;
}

function showTagModel(): string
{
    return new class extends Model
    {
        protected $table = 'tags';

        protected $guarded = [];

        public $timestamps = false;
    }::class;
}

// ---------------------------------------------------------------------------
// Schema
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    foreach (['products', 'teachers', 'course_details', 'chapters', 'ratings', 'tags', 'courses'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('products', fn (Blueprint $t) => $t->id());
    Schema::create('teachers', function (Blueprint $t): void {
        $t->id();
        $t->text('profile')->nullable();
    });
    Schema::create('course_details', function (Blueprint $t): void {
        $t->id();
        $t->text('description')->nullable();
        $t->text('product_desc')->nullable();
        $t->text('faq')->nullable();
    });
    Schema::create('chapters', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('course_id')->nullable();
        $t->unsignedBigInteger('parent_id')->nullable();
    });
    Schema::create('ratings', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('course_id')->nullable();
        $t->unsignedBigInteger('user_id')->nullable();
        $t->timestamp('created_at')->nullable();
    });
    Schema::create('tags', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('course_id')->nullable();
    });
    Schema::create('courses', function (Blueprint $t): void {
        $t->id();
        $t->string('ulid')->unique();
        $t->tinyInteger('status')->default(1);
        $t->unsignedBigInteger('product_id')->nullable();
        $t->unsignedBigInteger('teacher_id')->nullable();
        $t->unsignedBigInteger('detail_id')->nullable();
    });

    config()->set('course-core.models.course', showCourseModel());
});

// ---------------------------------------------------------------------------
// NullRichContentRenderer
// ---------------------------------------------------------------------------

it('NullRichContentRenderer passes string content through', function (): void {
    $r = new NullRichContentRenderer;

    expect($r->renderContent('hello'))->toBe('hello')
        ->and($r->renderContent(null))->toBeNull()
        ->and($r->renderContent(''))->toBeNull();
});

it('NullRichContentRenderer renders faqs preserving question and string answer', function (): void {
    $r = new NullRichContentRenderer;

    $result = $r->renderFaqs([
        ['question' => 'Q1', 'answer' => 'A1'],
        ['question' => 'Q2', 'answer' => ['not-a-string']],
        ['question' => 'Q3'],
    ]);

    expect($result)->toHaveCount(3)
        ->and($result[0])->toBe(['question' => 'Q1', 'answer' => 'A1'])
        ->and($result[1]['answer'])->toBeNull()
        ->and($result[2]['answer'])->toBeNull();
});

// ---------------------------------------------------------------------------
// RichContentRendererContract binding
// ---------------------------------------------------------------------------

it('resolves NullRichContentRenderer when config is null', function (): void {
    config()->set('course-core.rich_content_renderer', null);

    expect(app(RichContentRendererContract::class))->toBeInstanceOf(NullRichContentRenderer::class);
});

// ---------------------------------------------------------------------------
// CourseFrontendService::showData
// ---------------------------------------------------------------------------

function makeShowService(?CourseAccessResolver $access = null, ?RichContentRendererContract $rich = null): CourseFrontendService
{
    return new CourseFrontendService(
        search: new NullCourseSearch,
        accessResolver: $access ?? new NullCourseAccessResolver,
        richContent: $rich ?? new NullRichContentRenderer,
    );
}

it('returns null when course is not found', function (): void {
    $service = makeShowService();

    expect($service->showData('nonexistent-ulid'))->toBeNull();
});

it('returns null when course has no product', function (): void {
    $courseClass = showCourseModel();
    $courseClass::create(['ulid' => 'abc', 'status' => 1, 'product_id' => null]);

    expect(makeShowService()->showData('abc'))->toBeNull();
});

it('returns null for invalid (status=0) course', function (): void {
    $productClass = showProductModel();
    $product = $productClass::create([]);

    $courseClass = showCourseModel();
    $courseClass::create(['ulid' => 'draft', 'status' => 0, 'product_id' => $product->id]);

    expect(makeShowService()->showData('draft'))->toBeNull();
});

it('returns course model with relations for a valid course', function (): void {
    $productClass = showProductModel();
    $product = $productClass::create([]);

    $courseClass = showCourseModel();
    $course = $courseClass::create(['ulid' => 'valid-ulid', 'status' => 1, 'product_id' => $product->id]);

    $result = makeShowService()->showData('valid-ulid');

    expect($result)->not->toBeNull()
        ->and($result->ulid)->toBe('valid-ulid');
});

it('sets purchased=false for unauthenticated user', function (): void {
    $productClass = showProductModel();
    $product = $productClass::create([]);

    $courseClass = showCourseModel();
    $courseClass::create(['ulid' => 'p-ulid', 'status' => 1, 'product_id' => $product->id]);

    $result = makeShowService()->showData('p-ulid', user: null);

    expect($result->purchased)->toBeFalse();
});

it('sets purchased=true when access resolver confirms purchase', function (): void {
    $productClass = showProductModel();
    $product = $productClass::create([]);

    $courseClass = showCourseModel();
    $courseClass::create(['ulid' => 'bought', 'status' => 1, 'product_id' => $product->id]);

    $alwaysPurchased = new class implements CourseAccessResolver
    {
        public function canViewCourse(?Authenticatable $u, Model $c): bool
        {
            return true;
        }

        public function canAccessUnit(?Authenticatable $u, Model $c, Model $unit): bool
        {
            return true;
        }

        public function hasPurchasedCourse(?Authenticatable $u, Model $c): bool
        {
            return true;
        }
    };

    $fakeUser = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 1;
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
            return '';
        }
    };

    $result = makeShowService(access: $alwaysPurchased)->showData('bought', user: $fakeUser);

    expect($result->purchased)->toBeTrue();
});

it('renders teacher profile via RichContentRendererContract', function (): void {
    $teacherClass = showTeacherModel();
    $teacher = $teacherClass::create(['profile' => 'raw profile']);

    $productClass = showProductModel();
    $product = $productClass::create([]);

    $courseClass = showCourseModel();
    $courseClass::create(['ulid' => 'teach-ulid', 'status' => 1, 'product_id' => $product->id, 'teacher_id' => $teacher->id]);

    $stubRenderer = new class implements RichContentRendererContract
    {
        public string $captured = '';

        public function renderContent(mixed $content): ?string
        {
            $this->captured = (string) $content;

            return 'RENDERED:'.$content;
        }

        public function renderFaqs(mixed $faqData): array
        {
            return [];
        }
    };

    $result = makeShowService(rich: $stubRenderer)->showData('teach-ulid');

    expect($stubRenderer->captured)->toBe('raw profile')
        ->and($result->getRelationValue('teacher')->profile)->toBe('RENDERED:raw profile');
});

it('renders detail description and faqs via RichContentRendererContract', function (): void {
    $detailClass = showDetailModel();
    $detail = $detailClass::create([
        'description' => 'desc content',
        'product_desc' => 'prod desc',
        'faq' => json_encode([['question' => 'Q1', 'answer' => 'A1']]),
    ]);

    $productClass = showProductModel();
    $product = $productClass::create([]);

    $courseClass = showCourseModel();
    $courseClass::create(['ulid' => 'detail-ulid', 'status' => 1, 'product_id' => $product->id, 'detail_id' => $detail->id]);

    $result = makeShowService()->showData('detail-ulid');

    $loadedDetail = $result->getRelationValue('detail');
    expect($loadedDetail->description)->toBe('desc content')
        ->and($loadedDetail->product_desc)->toBe('prod desc')
        ->and($loadedDetail->faq)->toBeArray()
        ->and($loadedDetail->faq[0]['question'])->toBe('Q1');
});
