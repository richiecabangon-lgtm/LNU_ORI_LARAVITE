<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ResearchSubmission extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['research_type_label', 'has_authors'];

    protected $fillable = [
        'uuid',
        'reference_code',
        'faculty_id',
        'academic_year_id',
        'college_id',
        'department_id',
        'title',
        'research_type',
        'abstract',
        'authors',
        'venue',
        'publisher',
        'doi',
        'issn_isbn',
        'indexing',
        'publication_date',
        'faculty_role_rank',
        'claimed_points',
        'faculty_remarks',
        'status',
        'submitted_at',
        'evaluated_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'authors' => 'array',
            'publication_date' => 'date',
            'claimed_points' => 'decimal:2',
            'submitted_at' => 'datetime',
            'evaluated_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $submission): void {
            if (empty($submission->uuid)) {
                $submission->uuid = (string) Str::uuid();
            }

            if (empty($submission->reference_code)) {
                $submission->reference_code = self::generateReferenceCode();
            }

            $submission->normalized_title = self::normalizeTitle((string) $submission->title);
            $submission->normalized_title_hash = hash('sha256', $submission->normalized_title);
        });

        static::updating(function (self $submission): void {
            if ($submission->isDirty('title')) {
                $submission->normalized_title = self::normalizeTitle((string) $submission->title);
                $submission->normalized_title_hash = hash('sha256', $submission->normalized_title);
            }
        });

        static::deleting(function (self $submission): void {
            if (! $submission->isForceDeleting()) {
                $submission->normalized_title = '__deleted__'.$submission->id;
                $submission->normalized_title_hash = hash('sha256', $submission->normalized_title);
                $submission->saveQuietly();
            }
        });
    }

    public static function normalizeTitle(string $title): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $title) ?? $title));
    }

    public static function generateReferenceCode(): string
    {
        do {
            $code = 'ORI-'.now()->format('Y').'-'.strtoupper(Str::random(6));
        } while (self::query()->where('reference_code', $code)->exists());

        return $code;
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'faculty_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function researchType(): BelongsTo
    {
        return $this->belongsTo(ResearchType::class, 'research_type', 'slug')->withTrashed();
    }

    public function sdgs(): BelongsToMany
    {
        return $this->belongsToMany(Sdg::class, 'research_submission_sdg');
    }

    public function researchAgendas(): BelongsToMany
    {
        return $this->belongsToMany(ResearchAgenda::class, 'research_agenda_submission');
    }

    public function getResearchTypeLabelAttribute(): string
    {
        $type = $this->relationLoaded('researchType')
            ? $this->researchType
            : $this->researchType()->withTrashed()->first();

        return $type?->name ?? str($this->research_type)->replace('_', ' ')->title()->toString();
    }

    public function getHasAuthorsAttribute(): bool
    {
        // Only trusted when the detail pages load the flag; lists default to true to avoid extra queries.
        return ! $this->relationLoaded('researchType')
            || ! $this->researchType?->hasAttribute('has_authors')
            || $this->researchType->has_authors;
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SubmissionAttachment::class);
    }

    public function authorshipAllocations(): HasMany
    {
        return $this->hasMany(ResearchSubmissionAuthor::class)->orderBy('author_order');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(Evaluation::class);
    }

    public function approval(): HasOne
    {
        return $this->hasOne(Approval::class);
    }

    public function fpesPoints(): float
    {
        if ($this->status === SubmissionStatus::Rejected || $this->approval?->decision === 'rejected') {
            return 0.0;
        }

        return (float) ($this->approval?->final_points
            ?? $this->evaluation?->awarded_points
            ?? $this->claimed_points
            ?? 0);
    }

    public function pointsForFaculty(User $faculty): float
    {
        $authors = $this->relationLoaded('authorshipAllocations')
            ? $this->authorshipAllocations
            : $this->authorshipAllocations()->get();
        $author = $authors->firstWhere('faculty_id', $faculty->id);

        if ($author) {
            $allocatedTotal = (float) $authors->sum('allocated_points');

            return $allocatedTotal > 0
                ? round($this->fpesPoints() * ((float) $author->allocated_points / $allocatedTotal), 2)
                : 0.0;
        }

        return $this->faculty_id === $faculty->id ? $this->fpesPoints() : 0.0;
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class)->latest();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
