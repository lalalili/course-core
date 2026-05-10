<?php

use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Support\NullCourseAccessResolver;

it('denies course viewing by default', function (): void {
    $resolver = new NullCourseAccessResolver();
    $course = new class () extends Model {};

    expect($resolver->canViewCourse(null, $course))->toBeFalse();
});

it('only allows free units by default', function (): void {
    $resolver = new NullCourseAccessResolver();
    $course = new class () extends Model {};
    $freeUnit = new class (['isFree' => true]) extends Model {
        protected $guarded = [];
    };
    $paidUnit = new class (['isFree' => false]) extends Model {
        protected $guarded = [];
    };

    expect($resolver->canAccessUnit(null, $course, $freeUnit))->toBeTrue()
        ->and($resolver->canAccessUnit(null, $course, $paidUnit))->toBeFalse();
});
