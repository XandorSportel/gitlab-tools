<?php

namespace App\Models;

use Database\Factories\PlanningTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['title', 'description'])]
#[Touches(['issue'])]
class PlanningTask extends Model
{
    /** @use HasFactory<PlanningTaskFactory> */
    use HasFactory;

    /** @return array{completed_at: string} */
    protected function casts(): array
    {
        return ['completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PlanningIssue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(PlanningIssue::class, 'planning_issue_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<User, $this> */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->orderBy('users.name')->orderBy('users.id');
    }
}
