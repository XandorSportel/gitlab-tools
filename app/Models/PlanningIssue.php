<?php

namespace App\Models;

use Database\Factories\PlanningIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['gitlab_instance', 'gitlab_issue_id'])]
class PlanningIssue extends Model
{
    /** @use HasFactory<PlanningIssueFactory> */
    use HasFactory;

    /** @return BelongsToMany<PlanningTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PlanningTag::class)->orderBy('planning_tags.id');
    }

    /** @return HasMany<PlanningTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(PlanningTask::class);
    }
}
