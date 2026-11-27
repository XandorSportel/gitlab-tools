<?php

namespace App\Models;

use Database\Factories\PlanningTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'name_key'])]
class PlanningTag extends Model
{
    /** @use HasFactory<PlanningTagFactory> */
    use HasFactory;

    public static function nameKey(string $name): string
    {
        return hash('sha256', mb_strtolower($name));
    }

    /** @return BelongsToMany<PlanningIssue, $this> */
    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(PlanningIssue::class);
    }
}
