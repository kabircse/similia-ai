<?php

namespace Tests\Feature;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class KnowledgeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_books_and_book_sections_can_be_imported_as_embedded_knowledge_chunks(): void
    {
        $base = storage_path('app/testing/knowledge-import');
        File::deleteDirectory($base);
        File::ensureDirectoryExists($base);

        File::put($base.'/books.csv', implode("\n", [
            'id,code,title,author,language,edition,source_ref',
            '2,organon_kent_en,Organon of Medicine Commentary,"Samuel Hahnemann; J. T. Kent",en,6th,',
            '3,relationship_of_remedies,Relationship of Remedies,Dr. R. Gibson Miller,en,,',
        ]));

        File::put($base.'/book_sections.csv', implode("\n", [
            'id,book_code,section_no,title,body,summary,source_ref',
            '1,organon_kent_en,1,Highest ideal of cure,"# Aphorism 1
The highest ideal of cure is rapid gentle and permanent restoration of health.",Physician and cure,§1',
            '2,relationship_of_remedies,1,Complementary remedies,"# Complementary
Remedies may be complementary antidotal or inimical in relationship.",Remedy relationship,',
        ]));

        $this->artisan('import:legacy-books', [
            'path' => 'storage/app/testing/knowledge-import/books.csv',
            '--source' => 'legacy_sql',
        ])->assertExitCode(0);

        $this->artisan('import:legacy-book-sections', [
            'path' => 'storage/app/testing/knowledge-import/book_sections.csv',
            '--source' => 'legacy_sql',
        ])->assertExitCode(0);

        $this->assertSame(2, KnowledgeSource::count());

        $this->assertDatabaseHas('knowledge_sources', [
            'code' => 'organon_kent_en',
            'source_type' => 'organon',
        ]);

        $this->assertDatabaseHas('knowledge_sources', [
            'code' => 'relationship_of_remedies',
            'source_type' => 'relationship',
        ]);

        $this->assertGreaterThanOrEqual(2, KnowledgeChunk::count());

        $this->assertGreaterThan(
            0,
            KnowledgeChunk::whereNotNull('embedding')->count()
        );

        $this->assertDatabaseHas('knowledge_chunks', [
            'book_code' => 'organon_kent_en',
            'source_type' => 'organon',
            'section_no' => 1,
        ]);

        File::deleteDirectory($base);
    }

    public function test_authenticated_user_can_search_knowledge_chunks(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $base = storage_path('app/testing/knowledge-search');
        File::deleteDirectory($base);
        File::ensureDirectoryExists($base);

        File::put($base.'/books.csv', implode("\n", [
            'id,code,title,author,language,edition,source_ref',
            '2,organon_kent_en,Organon of Medicine Commentary,"Samuel Hahnemann; J. T. Kent",en,6th,',
        ]));

        File::put($base.'/book_sections.csv', implode("\n", [
            'id,book_code,section_no,title,body,summary,source_ref',
            '1,organon_kent_en,1,Highest ideal of cure,"# Aphorism 1
The highest ideal of cure is rapid gentle and permanent restoration of health.",Physician and cure,§1',
        ]));

        $this->artisan('import:legacy-books', [
            'path' => 'storage/app/testing/knowledge-search/books.csv',
            '--source' => 'legacy_sql',
        ])->assertExitCode(0);

        $this->artisan('import:legacy-book-sections', [
            'path' => 'storage/app/testing/knowledge-search/book_sections.csv',
            '--source' => 'legacy_sql',
        ])->assertExitCode(0);

        $this->actingAs($doctor)
            ->getJson('/api/knowledge/search?q=highest%20ideal%20of%20cure&source_type=organon&limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.book_code', 'organon_kent_en');

        File::deleteDirectory($base);
    }
}
