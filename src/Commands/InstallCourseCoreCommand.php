<?php

namespace Lalalili\CourseCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class InstallCourseCoreCommand extends Command
{
    protected $signature = 'course-core:install {--force : Overwrite existing published files}';

    protected $description = 'Publish course-core config, model stubs, migration stubs, and scaffolding.';

    public function handle(Filesystem $files): int
    {
        $force = (bool) $this->option('force');

        $this->call('vendor:publish', [
            '--tag' => 'course-core-config',
            '--force' => $force,
        ]);

        $this->publishModelStubs($files, $force);
        $this->publishMigrationStub($files, $force);
        $this->publishRatingsMigrationStub($files, $force);
        $this->publishControllerStub($files, $force);
        $this->publishRoutesStub($files, $force);

        $this->info('course-core install files are ready.');

        return self::SUCCESS;
    }

    protected function publishModelStubs(Filesystem $files, bool $force): void
    {
        $namespace = app()->getNamespace().'Models';
        $sourceDirectory = __DIR__.'/../../stubs/models';
        $targetDirectory = app_path('Models');

        $files->ensureDirectoryExists($targetDirectory);

        foreach ($files->files($sourceDirectory) as $file) {
            $target = $targetDirectory.'/'.Str::before($file->getFilename(), '.stub');

            if ($files->exists($target) && ! $force) {
                $this->line("Skipped existing model: {$target}");

                continue;
            }

            $contents = str_replace('{{ namespace }}', $namespace, $files->get($file->getPathname()));
            $files->put($target, $contents);
            $this->line("Published model: {$target}");
        }
    }

    protected function publishMigrationStub(Filesystem $files, bool $force): void
    {
        $targetDirectory = database_path('migrations');
        $target = $targetDirectory.'/'.date('Y_m_d_His').'_create_course_core_tables.php';

        if (! $force && collect($files->files($targetDirectory))->contains(
            fn ($file): bool => Str::endsWith($file->getFilename(), '_create_course_core_tables.php')
        )) {
            $this->line('Skipped existing course-core migration.');

            return;
        }

        $files->copy(__DIR__.'/../../stubs/database/migrations/create_course_core_tables.php.stub', $target);
        $this->line("Published migration: {$target}");
    }

    protected function publishRatingsMigrationStub(Filesystem $files, bool $force): void
    {
        $targetDirectory = database_path('migrations');
        $target = $targetDirectory.'/'.date('Y_m_d_His').'_create_ratings_table.php';

        if (! $force && collect($files->files($targetDirectory))->contains(
            fn ($file): bool => Str::endsWith($file->getFilename(), '_create_ratings_table.php')
        )) {
            $this->line('Skipped existing ratings migration.');

            return;
        }

        $files->copy(__DIR__.'/../../stubs/database/migrations/create_ratings_table.php.stub', $target);
        $this->line("Published migration: {$target}");
    }

    protected function publishControllerStub(Filesystem $files, bool $force): void
    {
        $namespace = rtrim(app()->getNamespace(), '\\').'\\Http\\Controllers';
        $targetDirectory = app_path('Http/Controllers');
        $target = $targetDirectory.'/CourseController.php';

        if ($files->exists($target) && ! $force) {
            $this->line("Skipped existing controller: {$target}");

            return;
        }

        $files->ensureDirectoryExists($targetDirectory);

        $contents = str_replace('{{ namespace }}', $namespace, $files->get(__DIR__.'/../../stubs/http/CourseController.php.stub'));
        $files->put($target, $contents);
        $this->line("Published controller: {$target}");
    }

    protected function publishRoutesStub(Filesystem $files, bool $force): void
    {
        $namespace = rtrim(app()->getNamespace(), '\\').'\\Http\\Controllers';
        $target = base_path('routes/course.php');

        if ($files->exists($target) && ! $force) {
            $this->line("Skipped existing routes file: {$target}");

            return;
        }

        $contents = str_replace('{{ namespace }}', $namespace, $files->get(__DIR__.'/../../stubs/routes/course.php.stub'));
        $files->put($target, $contents);
        $this->line("Published routes: {$target}");
    }
}
