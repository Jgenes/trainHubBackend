<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Course extends Model
{
    /**
     * Statuses that must never appear in the public catalogue.
     *
     * The React catalogue uses the same deny-list. Production databases also
     * contain legacy values from older API versions, so the public scope is
     * intentionally a case-insensitive deny-list rather than a hard-coded list
     * of "Published" and "Draft" spellings.
     */
    public const HIDDEN_PUBLIC_STATUSES = [
        'closed', 'suspended', 'archived', 'rejected', 'deleted',
        'unpublished', 'inactive', 'disabled',
    ];

    protected $fillable = [
        'provider_id', 'title', 'category', 'mode',
        'short_description', 'long_description', 'learning_outcomes',
        'skills', 'requirements', 'contents', 'videos', 'handouts',
        'status', 'banner',
    ];

    protected function casts(): array
    {
        return [
            'learning_outcomes' => 'array',
            'skills'            => 'array',
            'requirements'      => 'array',
            'contents'          => 'array',
            'videos'            => 'array',
            'handouts'          => 'array',
        ];
    }

    /** Canonical DB values are Draft / Published / Closed. */
    public static function normalizeStatus(?string $status): string
    {
        return match (strtoupper(trim((string) $status))) {
            'PUBLISHED', 'PUBLISH', 'APPROVED', 'ACTIVE' => 'Published',
            'DRAFT', 'PENDING'                           => 'Draft',
            'CLOSED', 'SUSPENDED', 'REJECTED', 'ARCHIVED',
            'DELETED', 'UNPUBLISHED', 'INACTIVE', 'DISABLED' => 'Closed',
            default                                      => 'Draft',
        };
    }

    /**
     * Courses the public catalogue may show.
     *
     * New courses are saved as Draft and the SPA deliberately shows them, so
     * this scope hides withdrawn/closed records rather than requiring one of
     * two exact status strings. It also accepts case variations and legacy
     * values such as APPROVED, ACTIVE or PENDING from older deployments, plus
     * rows whose status was never set.
     */
    public function scopePublished(Builder $query): Builder
    {
        $statusColumn = $query->getModel()->qualifyColumn('status');
        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($statusColumn);
        $normalisedStatus = DB::raw('LOWER(TRIM('.$wrappedColumn.'))');

        return $query->where(function (Builder $nested) use ($statusColumn, $normalisedStatus) {
            $nested->whereNull($statusColumn)
                ->orWhereNotIn($normalisedStatus, self::HIDDEN_PUBLIC_STATUSES);
        });
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function cohorts()
    {
        return $this->hasMany(Cohort::class, 'course_id');
    }

    public function notes()          { return $this->hasMany(Note::class); }
    public function videos()         { return $this->hasMany(Video::class); }
    public function announcements()  { return $this->hasMany(Announcement::class); }
    public function learningTools()  { return $this->hasMany(LearningTool::class); }
    public function questions()      { return $this->hasMany(Question::class); }
    public function reviews()        { return $this->hasMany(Review::class); }
    public function enrollments()    { return $this->hasMany(Enrollment::class, 'course_id'); }
}
