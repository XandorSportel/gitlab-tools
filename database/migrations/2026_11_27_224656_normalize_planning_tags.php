<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->char('name_key', 64)->unique();
            $table->timestamps();
        });

        Schema::create('planning_issue_planning_tag', function (Blueprint $table) {
            $table->foreignId('planning_issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('planning_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['planning_issue_id', 'planning_tag_id']);
        });

        DB::table('planning_issues')->select('id', 'tags')->whereNotNull('tags')->chunkById(200, function ($issues): void {
            $timestamp = now();

            foreach ($issues as $issue) {
                foreach (json_decode($issue->tags, true, flags: JSON_THROW_ON_ERROR) ?? [] as $name) {
                    $key = hash('sha256', mb_strtolower($name));
                    DB::table('planning_tags')->insertOrIgnore([
                        'name' => $name, 'name_key' => $key, 'created_at' => $timestamp, 'updated_at' => $timestamp,
                    ]);
                    $tagId = DB::table('planning_tags')->where('name_key', $key)->value('id');
                    DB::table('planning_issue_planning_tag')->insertOrIgnore([
                        'planning_issue_id' => $issue->id, 'planning_tag_id' => $tagId,
                    ]);
                }
            }
        });

        Schema::table('planning_issues', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }

    public function down(): void
    {
        Schema::table('planning_issues', function (Blueprint $table) {
            $table->json('tags')->nullable();
        });

        DB::table('planning_issues')->select('id')->chunkById(200, function ($issues): void {
            $tags = DB::table('planning_issue_planning_tag')
                ->join('planning_tags', 'planning_tags.id', '=', 'planning_issue_planning_tag.planning_tag_id')
                ->whereIn('planning_issue_id', $issues->pluck('id'))
                ->orderBy('planning_tags.id')
                ->get(['planning_issue_id', 'name'])
                ->groupBy('planning_issue_id');

            foreach ($issues as $issue) {
                if ($tags->has($issue->id)) {
                    DB::table('planning_issues')->where('id', $issue->id)->update([
                        'tags' => $tags[$issue->id]->pluck('name')->toJson(JSON_THROW_ON_ERROR),
                    ]);
                }
            }
        });

        Schema::dropIfExists('planning_issue_planning_tag');
        Schema::dropIfExists('planning_tags');
    }
};
