<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lalalili\CourseCore\Contracts\CourseAccessResolver;
use Lalalili\CourseCore\Exceptions\CourseConfigurationException;
use Lalalili\CourseCore\Services\CoursePlaybackService;

function courseCoreModel(array $attributes = []): Model
{
    return new class($attributes) extends Model
    {
        protected $guarded = [];

        public $timestamps = false;
    };
}

function courseCoreUser(int $id): Authenticatable
{
    return new class($id) implements Authenticatable
    {
        public function __construct(protected int $id) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
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

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value) {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };
}

it('returns the first free unit without creating history', function (): void {
    $service = new CoursePlaybackService(new class implements CourseAccessResolver
    {
        public function canViewCourse(?Authenticatable $user, Model $course): bool
        {
            return true;
        }

        public function canAccessUnit(?Authenticatable $user, Model $course, Model $unit): bool
        {
            return false;
        }

        public function hasPurchasedCourse(?Authenticatable $user, Model $course): bool
        {
            return false;
        }
    });

    $unit = courseCoreModel(['id' => 10, 'is_free' => true]);
    $chapter = courseCoreModel(['id' => 1]);
    $chapter->setRelation('units', collect([$unit]));

    expect($service->initializeUnit(courseCoreModel(), collect([$chapter]), collect()))->toBe($unit);
});

it('fails loudly when a paid unit needs history but history model is not configured', function (): void {
    config()->set('course-core.models.history', null);

    $service = new CoursePlaybackService(new class implements CourseAccessResolver
    {
        public function canViewCourse(?Authenticatable $user, Model $course): bool
        {
            return true;
        }

        public function canAccessUnit(?Authenticatable $user, Model $course, Model $unit): bool
        {
            return true;
        }

        public function hasPurchasedCourse(?Authenticatable $user, Model $course): bool
        {
            return true;
        }
    });

    $unit = courseCoreModel(['id' => 10, 'course_id' => 5, 'parent_id' => 1, 'is_free' => false]);
    $chapter = courseCoreModel(['id' => 1]);
    $chapter->setRelation('units', Collection::make([$unit]));

    $service->initializeUnit(courseCoreModel(['id' => 5]), collect([$chapter]), collect(), courseCoreUser(9));
})->throws(CourseConfigurationException::class);
