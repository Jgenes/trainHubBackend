<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    /** Statuses that mean the money was received. */
    public const PAID_STATUSES = ['COMPLETED', 'PAID', 'SUCCESS'];

    /** Statuses that mean the transaction will never settle. */
    public const FAILED_STATUSES = ['FAILED', 'INVALID', 'CANCELLED', 'REFUNDED'];

    protected $fillable = [
        'reference', 'user_id', 'course_id', 'cohort_id',
        'first_name', 'last_name', 'email', 'phone_number',
        'amount', 'currency', 'description', 'status', 'tracking_id',
        'organization', 'position', 'street', 'region', 'city', 'postal',
        'user_name', 'expires_at', 'payment_method', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'expires_at'  => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function cohort()
    {
        return $this->belongsTo(Cohort::class, 'cohort_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'payment_id');
    }

    public function receipt()
    {
        return $this->hasOne(Receipt::class, 'payment_id');
    }

    public function disputes()
    {
        return $this->hasMany(Dispute::class, 'payment_id');
    }

    /** The gateway reports the money as received. */
    public function isPaid(): bool
    {
        return in_array(strtoupper((string) $this->status), self::PAID_STATUSES, true);
    }

    /**
     * Paid *and* confirmed — either by the PesaPal callback/status API or by an
     * authorised manual confirmation. This is the only thing that may unlock a
     * course; a bare "paid" status that no gateway confirmed is not enough.
     */
    public function isVerifiedPaid(): bool
    {
        return $this->isPaid() && $this->verified_at !== null;
    }

    /** Course access rule used by every learner-facing endpoint. */
    public function grantsCourseAccess(): bool
    {
        return $this->isVerifiedPaid();
    }

    /** Case-insensitive "money received" filter (legacy rows are lowercase). */
    public function scopePaid(Builder $query): Builder
    {
        $placeholders = implode(', ', array_fill(0, count(self::PAID_STATUSES), '?'));

        return $query->whereRaw(
            'UPPER('.$query->getModel()->qualifyColumn('status').') IN ('.$placeholders.')',
            self::PAID_STATUSES
        );
    }

    /** Filter for payments that were actually confirmed (see isVerifiedPaid). */
    public function scopeVerifiedPaid(Builder $query): Builder
    {
        return $query->paid()->whereNotNull($query->getModel()->qualifyColumn('verified_at'));
    }

    /** True only when the user holds a confirmed payment for the course. */
    public static function hasCourseAccess(int|string|null $userId, int|string|null $courseId): bool
    {
        if ($userId === null || $courseId === null || $courseId === '') {
            return false;
        }

        return static::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->verifiedPaid()
            ->exists();
    }

    /**
     * Mirror a settled payment as an enrollment row (cohort seats, provider
     * reports). Only confirmed payments may create one.
     */
    public function ensureEnrollment(): ?Enrollment
    {
        if (! $this->grantsCourseAccess()) {
            return null;
        }

        return Enrollment::firstOrCreate(
            [
                'user_id'   => $this->user_id,
                'course_id' => $this->course_id,
                'cohort_id' => $this->cohort_id,
            ],
            [
                'status'      => 'PAID',
                'amount'      => $this->amount,
                'provider_id' => $this->course?->provider_id,
            ]
        );
    }
}
