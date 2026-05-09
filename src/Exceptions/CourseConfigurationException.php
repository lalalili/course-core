<?php

namespace Lalalili\CourseCore\Exceptions;

use RuntimeException;

class CourseConfigurationException extends RuntimeException
{
    public static function missingModel(string $key): self
    {
        return new self("The course-core.models.{$key} config value must be set to a valid model class.");
    }
}
