<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #296 — an analysis enters the record only through a citation, and only when a
 * professional reviewed it.
 *
 * The citation is polymorphic because both clinical documents that can
 * incorporate an analysis cite it: the evolution of the act and the treatment
 * plan. Append-only by design: there is no `updated_at`, both ends restrict
 * deletion (the analysis row is annulled, never deleted) and the pair is unique
 * so the same analysis cannot be cited twice in the same document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analysis_citations', function (Blueprint $table) {
            $table->id();
            $table->string('citable_type', 64);
            $table->unsignedBigInteger('citable_id');
            $table->foreignId('ai_image_analysis_id')->constrained()->restrictOnDelete();
            $table->foreignId('cited_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['citable_type', 'citable_id', 'ai_image_analysis_id'],
                'ai_citations_unique_pair',
            );
            $table->index(['citable_type', 'citable_id'], 'ai_citations_citable_index');
            $table->index('ai_image_analysis_id', 'ai_citations_analysis_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analysis_citations');
    }
};
