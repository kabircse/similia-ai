<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement('CREATE INDEX IF NOT EXISTS patients_name_trgm_idx ON patients USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS patients_phone_trgm_idx ON patients USING gin (phone gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS patients_address_trgm_idx ON patients USING gin (address gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS patients_notes_trgm_idx ON patients USING gin (notes gin_trgm_ops)');

        DB::statement('CREATE INDEX IF NOT EXISTS patient_visits_chief_complaint_trgm_idx ON patient_visits USING gin (chief_complaint gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS patient_visits_raw_case_text_trgm_idx ON patient_visits USING gin (raw_case_text gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS patient_visits_doctor_notes_trgm_idx ON patient_visits USING gin (doctor_notes gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS patient_visits_case_sections_trgm_idx ON patient_visits USING gin ((case_sections::text) gin_trgm_ops)');

        DB::statement('CREATE INDEX IF NOT EXISTS patient_prescriptions_remedy_name_trgm_idx ON patient_prescriptions USING gin (remedy_name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS patient_prescriptions_reason_trgm_idx ON patient_prescriptions USING gin (reason gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS patient_prescriptions_advice_trgm_idx ON patient_prescriptions USING gin (advice gin_trgm_ops)');

        DB::statement('CREATE INDEX IF NOT EXISTS remedy_suggestion_runs_snapshot_trgm_idx ON remedy_suggestion_runs USING gin ((case_snapshot::text) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS remedy_suggestion_items_summary_trgm_idx ON remedy_suggestion_items USING gin (summary gin_trgm_ops)');

        DB::statement('CREATE INDEX IF NOT EXISTS follow_up_analysis_summary_trgm_idx ON follow_up_analysis_runs USING gin (analysis_summary gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS follow_up_analysis_remedy_response_trgm_idx ON follow_up_analysis_runs USING gin (remedy_response_assessment gin_trgm_ops)');

        DB::statement('CREATE INDEX IF NOT EXISTS potency_guidance_summary_trgm_idx ON potency_guidance_runs USING gin (guidance_summary gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS potency_guidance_options_rationale_trgm_idx ON potency_guidance_options USING gin (rationale gin_trgm_ops)');

        DB::statement('CREATE INDEX IF NOT EXISTS remedy_relationship_summary_trgm_idx ON remedy_relationship_runs USING gin (relationship_summary gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS remedy_relationship_findings_summary_trgm_idx ON remedy_relationship_findings USING gin (summary gin_trgm_ops)');

        DB::statement('CREATE INDEX IF NOT EXISTS prescription_review_summary_trgm_idx ON prescription_review_runs USING gin (review_summary gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS prescription_review_checks_assessment_trgm_idx ON prescription_review_checks USING gin (ai_assessment gin_trgm_ops)');

        DB::statement('CREATE INDEX IF NOT EXISTS patient_handout_summary_trgm_idx ON patient_handout_runs USING gin (patient_summary gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS patient_handout_sections_content_trgm_idx ON patient_handout_sections USING gin (content gin_trgm_ops)');

        DB::statement('CREATE INDEX IF NOT EXISTS clinic_report_summary_trgm_idx ON clinic_report_runs USING gin (executive_summary gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS clinic_report_sections_content_trgm_idx ON clinic_report_sections USING gin (content gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS patients_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS patients_phone_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS patients_address_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS patients_notes_trgm_idx');

        DB::statement('DROP INDEX IF EXISTS patient_visits_chief_complaint_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS patient_visits_raw_case_text_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS patient_visits_doctor_notes_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS patient_visits_case_sections_trgm_idx');

        DB::statement('DROP INDEX IF EXISTS patient_prescriptions_remedy_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS patient_prescriptions_reason_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS patient_prescriptions_advice_trgm_idx');

        DB::statement('DROP INDEX IF EXISTS remedy_suggestion_runs_snapshot_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS remedy_suggestion_items_summary_trgm_idx');

        DB::statement('DROP INDEX IF EXISTS follow_up_analysis_summary_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS follow_up_analysis_remedy_response_trgm_idx');

        DB::statement('DROP INDEX IF EXISTS potency_guidance_summary_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS potency_guidance_options_rationale_trgm_idx');

        DB::statement('DROP INDEX IF EXISTS remedy_relationship_summary_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS remedy_relationship_findings_summary_trgm_idx');

        DB::statement('DROP INDEX IF EXISTS prescription_review_summary_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS prescription_review_checks_assessment_trgm_idx');

        DB::statement('DROP INDEX IF EXISTS patient_handout_summary_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS patient_handout_sections_content_trgm_idx');

        DB::statement('DROP INDEX IF EXISTS clinic_report_summary_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS clinic_report_sections_content_trgm_idx');
    }
};
