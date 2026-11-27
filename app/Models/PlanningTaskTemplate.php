<?php

namespace App\Models;

use Database\Factories\PlanningTaskTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title'])]
class PlanningTaskTemplate extends Model
{
    /** @use HasFactory<PlanningTaskTemplateFactory> */
    use HasFactory;
}
