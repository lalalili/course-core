# Changelog

## 1.0.0

- **E-6**: Install scaffold。
- `Course.php.stub` 與 `CourseCategory.php.stub` 補 `scopeValid()` 範例實作（含 `Builder` import）。
- 新增 `stubs/http/CourseController.php.stub`：薄 controller，注入 `CourseFrontendService`；涵蓋 `index`、`category`、`search`、`show`、`classroom`、`syncProgress`、`fetchProgress`、`findClosestUnit` 八個方法。
- 新增 `stubs/routes/course.php.stub`：對應上述 controller 的路由定義，auth-protected group 含進度與導覽端點。
- `InstallCourseCoreCommand::handle()` 新增呼叫 `publishControllerStub()` 與 `publishRoutesStub()`；兩者均做 namespace 替換與既有檔案 skip 保護。
- 新增 `docs/course/frontend-data-contract.md`：各 service method 回傳形狀文件，含 TypeScript 型別參考。

## 0.9.0

- **E-5**: 進度 API 下沉。
- 新增 `CourseProgressService`（`src/Services/`）：`sync()`（含 completed no-regression）、`fetch()`，依賴 `config('course-core.models.history')`。
- `CourseFrontendService` 新增三個方法：
  - `syncProgress(Authenticatable, Model $course, int $chapterId, int $unitId, float $progress, ?string $lastWatchedAt, bool $completed)` — 解析 unit、驗證 `canAccessUnit`、委派 `CourseProgressService::sync()`；access 拒絕時拋 `AuthorizationException`，unit 不存在時拋 `ModelNotFoundException`。
  - `fetchProgress(Authenticatable, Model $course, int $chapterId, int $unitId)` — 同上驗證後委派 `fetch()`。
  - `findClosestUnit(Model $course, int $activeUnitId, ?Authenticatable $user)` — 查詢章節+單元，回傳 `{previousUnit, currentUnit, nextUnit}` navigation payload；無 user 或 access denied 時 prev/next 為 null。
- `CourseFrontendService` 建構式新增選配 `?CourseProgressService $progressService`（null 時 lazy 透過 `app()` 解析）。

## 0.8.0

- **E-4**: `CourseFrontendService::classroomData(Model $course, ?Authenticatable $user): array` — 教室播放頁資料組裝。
  - 回傳：`chapters`（含 units+video 關聯）、`courseHistory`、`currentUnit`、`chapterId`、`unitId`、`video`（`toFrontendArray()`）、`progress`（duration-based 0-100）、`purchased`、`myRating`。
  - 委派 `CoursePlaybackService::initializeUnit()` 決定當前單元；委派 `CourseUnitVideoResolver::resolve()` 解析影片。
  - Host 責任：存取控制 abort（無 currentUnit / canAccessUnit）、materials（Spatie media）、cartContent、Inertia render。
- `CourseFrontendService` 建構式新增選配 `?CourseUnitVideoResolver $videoResolver` 與 `?CoursePlaybackService $playbackService`（null 時 lazy 透過 `app()` 解析）。

## 0.7.0

- **E-3**: `VideoModelContract` — abstract interface for host video models (`videoProviderKey`, `resolvedProviderVideoId`, `getPlayerEmbedUrl`, `getProviderStatus`, `getTranscodeStatus`, `getDuration`, `getVideoMetadata`).
- `CourseUnitVideoPayload` — moved from host app into `Data\CourseUnitVideoPayload`; `toFrontendArray()` returns shape with `vimeoId`, `videoId`, `videoProvider`, `embedUrl`, `videoStatus`, `transcodeStatus`, `videoRecordId`.
- `CourseUnitVideoResolver` — moved from host app into `Services\CourseUnitVideoResolver`; uses `VideoModelContract` and `CourseVideoPlatformManager`; `fromLegacyUrl` is `protected` for host subclass override.

## 0.6.0

- **E-2**: `RichContentRendererContract` — `renderContent(?mixed): ?string` and `renderFaqs(mixed): list<array{question,answer}>`.
- `NullRichContentRenderer` — pass-through default; JSON-encodes non-string content.
- `CourseFrontendService::showData(string $ulid, ?Authenticatable $user): ?Model` — queries by ulid+valid scope, eager-loads all relations, applies rich content rendering and purchased flag via contracts.

## 0.5.0

- **E-1**: `CourseSearchContract` — `searchCourses(string $keyword): array{courses: LengthAwarePaginator, keyword: string}`.
- `NullCourseSearch` — default fallback returning empty paginator.
- `CourseFrontendService` — `listingData(int $perPage)`, `categoryData(int $categoryId, int $perPage)`, `searchData(string $keyword)`.
- `config('course-core.search')` — bind host search implementation.

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
