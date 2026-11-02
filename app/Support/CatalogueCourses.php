<?php

namespace App\Support;

use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One query path for every public catalogue screen.
 *
 * Normally this uses Course::published(). If a deployment has course rows but
 * none of them pass that scope — because of an old status spelling, a different
 * schema or a mistakenly withdrawn row — the emergency fallback returns the
 * existing non-deleted rows instead of an empty catalogue. It never kicks in
 * when at least one properly visible course exists.
 */
class CatalogueCourses
{
    private static bool $fallbackUsed = false;

    public static function query(): Builder
    {
        self::$fallbackUsed = false;

        if (! config('catalogue.include_hidden_when_empty', true)) {
            return Course::published();
        }

        try {
            if (Course::published()->exists()) {
                return Course::published();
            }
        } catch (\Throwable $e) {
            // A legacy schema may not have the status column at all.
            Log::warning('Public course scope failed; using catalogue fallback: '.$e->getMessage());
        }

        self::$fallbackUsed = true;
        Log::warning('No course matched the public scope; returning existing courses via the catalogue fallback.');

        $query = Course::query();

        if (Schema::hasColumn('courses', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    public static function get(?string $search = null): Collection
    {
        $query = self::query();

        if ($search !== null && $search !== '' && Schema::hasColumn('courses', 'title')) {
            $query->where('title', 'like', '%'.$search.'%');
        }

        $courses = self::applyEagerLoads($query)
            ->when(
                Schema::hasColumn('courses', 'created_at'),
                fn (Builder $builder) => $builder->latest()
            )
            ->get();

        return self::tagFallback($courses);
    }

    public static function find(int|string $id): ?Course
    {
        $course = self::applyEagerLoads(self::query())->find($id);

        if ($course && self::$fallbackUsed) {
            $course->setAttribute('catalogue_fallback', true);
        }

        return $course;
    }

    private static function applyEagerLoads(Builder $query): Builder
    {
        $with = [];

        if (Schema::hasTable('users')) {
            $with[] = 'provider:id,name';
        }
        if (Schema::hasTable('cohorts')) {
            $with[] = 'cohorts';
        }

        if ($with !== []) {
            $query->with($with);
        }
        if (Schema::hasTable('enrollments')) {
            $query->withCount('enrollments');
        }

        return $query;
    }

    private static function tagFallback(Collection $courses): Collection
    {
        if (self::$fallbackUsed) {
            $courses->each(fn (Course $course) => $course->setAttribute('catalogue_fallback', true));
        }

        return $courses;
    }
}
