<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lalalili\CourseCore\Exceptions\CourseConfigurationException;
use Lalalili\CourseCore\Services\CourseRatingService;

// ---------------------------------------------------------------------------
// Minimal in-memory models for the test
// ---------------------------------------------------------------------------

function ratingRateable(string $type = 'course', int|string $id = 1): Model
{
    $model = new class extends Model {
        public string $morphType = 'course';

        public int|string $morphId = 1;

        protected $guarded = [];

        public $timestamps = false;

        public function getMorphClass(): string
        {
            return $this->morphType;
        }

        public function getKey(): int|string
        {
            return $this->morphId;
        }
    };

    $model->morphType = $type;
    $model->morphId = $id;

    return $model;
}

// A minimal Eloquent model backed by the in-memory ratings table
function ratingModel(): string
{
    return new class extends Model {
        protected $table = 'ratings';

        protected $guarded = [];

        public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
        {
            return $this->belongsTo(get_class($this), 'user_id');
        }
    }::class;
}

// ---------------------------------------------------------------------------
// Set up the ratings table before each test (in-memory SQLite)
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    Schema::dropIfExists('ratings');
    Schema::create('ratings', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('rateable_type');
        $table->string('rateable_id');
        $table->unsignedTinyInteger('score');
        $table->text('title')->nullable();
        $table->text('comment')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['user_id', 'rateable_type', 'rateable_id'], 'user_rateable_unique');
    });
});

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('throws when rating model config is missing', function (): void {
    config()->set('course-core.models.rating', null);

    $service = new CourseRatingService;

    expect(fn () => $service->submit(ratingRateable(), 1, 'title', 'comment', 5))
        ->toThrow(CourseConfigurationException::class);
});

it('submits a new rating', function (): void {
    config()->set('course-core.models.rating', ratingModel());

    $service = new CourseRatingService;
    $rateable = ratingRateable('course', 10);

    $rating = $service->submit($rateable, 1, 'Great', 'Very good', 5);

    expect($rating->score)->toBe(5)
        ->and($rating->title)->toBe('Great')
        ->and($rating->comment)->toBe('Very good')
        ->and($rating->user_id)->toBe(1);
});

it('updates an existing rating instead of creating a duplicate', function (): void {
    config()->set('course-core.models.rating', ratingModel());

    $service = new CourseRatingService;
    $rateable = ratingRateable('course', 20);

    $service->submit($rateable, 2, 'Initial', 'Okay', 3);
    $updated = $service->submit($rateable, 2, 'Updated', 'Much better', 5);

    $model = ratingModel();
    $count = $model::where('user_id', 2)
        ->where('rateable_type', 'course')
        ->where('rateable_id', 20)
        ->count();

    expect($count)->toBe(1)
        ->and($updated->score)->toBe(5)
        ->and($updated->title)->toBe('Updated');
});

it('returns paginated ratings for a rateable', function (): void {
    config()->set('course-core.models.rating', ratingModel());

    $service = new CourseRatingService;
    $rateable = ratingRateable('course', 30);

    $service->submit($rateable, 3, 'A', 'comment', 4);
    $service->submit($rateable, 4, 'B', 'comment', 5);

    $paginator = $service->forRateable($rateable, 5);

    expect($paginator->total())->toBe(2);
});

it('returns the user rating for a rateable', function (): void {
    config()->set('course-core.models.rating', ratingModel());

    $service = new CourseRatingService;
    $rateable = ratingRateable('course', 40);

    $service->submit($rateable, 5, 'Mine', 'comment', 4);

    $found = $service->userRating($rateable, 5);
    $notFound = $service->userRating($rateable, 99);

    expect($found)->not->toBeNull()
        ->and($found->user_id)->toBe(5)
        ->and($notFound)->toBeNull();
});
