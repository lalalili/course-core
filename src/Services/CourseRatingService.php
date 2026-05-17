<?php

namespace Lalalili\CourseCore\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Exceptions\CourseConfigurationException;

class CourseRatingService
{
    /** @return class-string<Model> */
    protected function ratingModelClass(): string
    {
        $model = config('course-core.models.rating');

        if (! is_string($model) || ! class_exists($model)) {
            throw CourseConfigurationException::missingModel('rating');
        }

        return $model;
    }

    public function submit(Model $rateable, int $userId, string $title, string $comment, int $score): Model
    {
        $model = $this->ratingModelClass();

        return $model::updateOrCreate(
            [
                'user_id'       => $userId,
                'rateable_type' => $rateable->getMorphClass(),
                'rateable_id'   => $rateable->getKey(),
            ],
            [
                'title'   => $title,
                'comment' => $comment,
                'score'   => $score,
            ]
        );
    }

    public function forRateable(Model $rateable, int $perPage = 5): LengthAwarePaginator
    {
        $model = $this->ratingModelClass();

        return $model::with('user')
            ->where('rateable_type', $rateable->getMorphClass())
            ->where('rateable_id', $rateable->getKey())
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function userRating(Model $rateable, int $userId): ?Model
    {
        $model = $this->ratingModelClass();

        return $model::where('user_id', $userId)
            ->where('rateable_type', $rateable->getMorphClass())
            ->where('rateable_id', $rateable->getKey())
            ->first();
    }
}
