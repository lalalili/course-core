# Changelog

## 0.3.0

- **Breaking**: `CourseReadinessService` constructor signature changed from `(CourseProductResolver $resolver)` to `(iterable $checks, array $eagerLoad = [])`.
- Added extensible check pipeline: `CourseReadinessCheck` contract, `CourseReadinessContext` DTO, `CourseReadinessReport` collector.
- Added four built-in default checks: `BasicFieldsCheck`, `DetailCheck`, `ProductCheck`, `UnitsCheck`.
- `CourseReadinessService::DEFAULT_CHECKS` constant provides the default class list.
- `config('course-core.readiness.checks')` — null (default) uses DEFAULT_CHECKS; set to a class list to replace the pipeline entirely.
- `config('course-core.readiness.eager_load')` — relations to `loadMissing()` before running checks.
- `CourseReadinessResult` now has `summary()`, `hasWarnings()`, `hasSuggestions()` methods.

## 0.1.0

- Added install command, model stubs, migration stub, tests, and CI.
- Made Vimeo optional and changed the default video provider to a null provider.
- Changed default course access to deny course viewing unless the host app configures a resolver.
- Added explicit user context support to playback initialization.
