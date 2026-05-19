<?php

use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Contracts\CourseProductResolver;
use Lalalili\CourseCore\Readiness\BasicFieldsCheck;
use Lalalili\CourseCore\Readiness\DetailCheck;
use Lalalili\CourseCore\Readiness\ProductCheck;
use Lalalili\CourseCore\Readiness\UnitsCheck;
use Lalalili\CourseCore\Services\CourseReadinessService;

it('reports blocking issues for courses without required structure', function (): void {
    $course = new class extends Model
    {
        protected $guarded = [];
    };

    $result = readinessService()->evaluate($course);

    expect($result->isReady())->toBeFalse()
        ->and($result->blockingIssues)->toContain('Course title is required.')
        ->and($result->blockingIssues)->toContain('Course category is required.')
        ->and($result->blockingIssues)->toContain('Course detail is required.')
        ->and($result->blockingIssues)->toContain('At least one course chapter is required.');
});

it('accepts a course with detail, chapters, units, and video references', function (): void {
    $course = readinessModel([
        'title' => 'Course title',
        'course_category_id' => 1,
    ]);
    $detail = readinessModel(['content' => 'Course content']);
    $chapter = readinessModel(['title' => 'Chapter 1']);
    $unit = readinessModel(['title' => 'Unit 1', 'video_url' => 'https://iframe.videodelivery.net/video-1']);
    $chapter->setRelation('units', collect([$unit]));
    $course->setRelation('detail', $detail);
    $course->setRelation('chapters', collect([$chapter]));

    $result = readinessService()->evaluate($course);

    expect($result->isReady())->toBeTrue()
        ->and($result->warnings)->toBeEmpty();
});

it('can require product bindings and ready videos', function (): void {
    $product = readinessModel(['title' => 'Product']);
    $course = readinessModel([
        'title' => 'Course title',
        'course_category_id' => 1,
    ]);
    $course->setRelation('product', $product);
    $course->setRelation('detail', readinessModel(['content' => 'Course content']));

    $video = readinessModel(['provider_status' => 'processing', 'transcode_status' => 'in_progress']);
    $unit = readinessModel(['title' => 'Unit 1']);
    $unit->setRelation('video', $video);

    $chapter = readinessModel(['title' => 'Chapter 1']);
    $chapter->setRelation('units', collect([$unit]));
    $course->setRelation('chapters', collect([$chapter]));

    $resolver = new class implements CourseProductResolver
    {
        public function productForCourse(Model $course): ?Model
        {
            $product = $course->getRelationValue('product');

            return $product instanceof Model ? $product : null;
        }
    };

    $result = (new CourseReadinessService([
        new BasicFieldsCheck,
        new DetailCheck,
        new ProductCheck($resolver),
        new UnitsCheck,
    ]))->evaluate(
        course: $course,
        requireProduct: true,
        requireReadyVideos: true,
    );

    expect($result->isReady())->toBeFalse()
        ->and($result->blockingIssues)->toContain('Course unit [Unit 1] video is not ready.');
});

/**
 * @param  array<string, mixed>  $attributes
 */
if (! function_exists('readinessModel')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function readinessModel(array $attributes = []): Model
    {
        return new class($attributes) extends Model
        {
            protected $guarded = [];

            /**
             * @param  array<string, mixed>  $attributes
             */
            public function __construct(array $attributes = [])
            {
                parent::__construct($attributes);
                $this->exists = true;
            }
        };
    }
}

if (! function_exists('readinessService')) {
    function readinessService(): CourseReadinessService
    {
        $resolver = new class implements CourseProductResolver
        {
            public function productForCourse(Model $course): ?Model
            {
                return null;
            }
        };

        return new CourseReadinessService([
            new BasicFieldsCheck,
            new DetailCheck,
            new ProductCheck($resolver),
            new UnitsCheck,
        ]);
    }
}
