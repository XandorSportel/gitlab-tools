<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_issues', function (Blueprint $table) {
            $table->id();
            $table->char('gitlab_instance', 64);
            $table->unsignedBigInteger('gitlab_issue_id');
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->unique(['gitlab_instance', 'gitlab_issue_id']);
        });

        Schema::table('planning_tasks', function (Blueprint $table) {
            $table->foreignId('planning_issue_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('planning_milestone_id')->nullable();
            $table->string('planning_done_label')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['planning_milestone_id', 'planning_done_label']);
        });

        Schema::table('planning_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('planning_issue_id');
        });

        Schema::dropIfExists('planning_issues');
    }
};
