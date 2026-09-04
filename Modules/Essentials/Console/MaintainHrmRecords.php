<?php

namespace Modules\Essentials\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Essentials\Entities\EmployeeDocument;
use Modules\Essentials\Services\HrmAuditService;

class MaintainHrmRecords extends Command
{
    protected $signature = 'pos:maintainHrmRecords {--business=}';
    protected $description = 'Apply HR document, candidate, certificate and survey retention statuses';

    public function handle(HrmAuditService $audit): int
    {
        $business = $this->option('business') ? (int) $this->option('business') : null;
        $today = now()->toDateString();

        if (Schema::hasTable('hrm_employee_documents')) {
            EmployeeDocument::query()->when($business, fn ($query) => $query->where('business_id', $business))
                ->where('status', 'active')->whereDate('expires_on', '<', $today)->orderBy('id')->chunkById(100, function ($documents) use ($audit) {
                    foreach ($documents as $document) {
                        $document->update(['status' => 'expired']);
                        $audit->record($document->business_id, 'document.expired', EmployeeDocument::class, $document->id, ['status' => 'active'], ['status' => 'expired']);
                    }
                });

            EmployeeDocument::query()->when($business, fn ($query) => $query->where('business_id', $business))
                ->whereIn('status', ['active', 'expired'])->where('legal_hold', false)->whereDate('retention_until', '<', $today)->orderBy('id')->chunkById(100, function ($documents) use ($audit) {
                    foreach ($documents as $document) {
                        // Retention expiry triggers review rather than irreversible
                        // deletion; HR must confirm the applicable local law first.
                        $before = $document->status;
                        $document->update(['status' => 'retention_review_due']);
                        $audit->record($document->business_id, 'document.retention_review_due', EmployeeDocument::class, $document->id, ['status' => $before], ['status' => 'retention_review_due']);
                    }
                });
        }

        if (Schema::hasTable('hrm_candidates') && Schema::hasTable('hrm_job_applications')) {
            DB::table('hrm_candidates')->when($business, fn ($query) => $query->where('business_id', $business))
            ->where('status', 'active')->whereDate('retention_until', '<', $today)->orderBy('id')->chunkById(100, function ($candidates) use ($audit) {
                foreach ($candidates as $candidate) {
                    $hired = DB::table('hrm_job_applications')->where('business_id', $candidate->business_id)->where('candidate_id', $candidate->id)->where('stage', 'hired')->exists();
                    if ($hired) continue;
                    if ($candidate->resume_path && Storage::disk('local')->exists($candidate->resume_path)) Storage::disk('local')->delete($candidate->resume_path);
                    DB::table('hrm_candidates')->where('business_id', $candidate->business_id)->where('id', $candidate->id)->update([
                        'first_name' => 'Retention', 'last_name' => 'Expired '.$candidate->id, 'email' => null, 'phone' => null, 'resume_path' => null,
                        'consents' => json_encode(['retention_expired_at' => now()->toIso8601String()]), 'status' => 'anonymized', 'updated_at' => now(),
                    ]);
                    $audit->record($candidate->business_id, 'recruitment.candidate_anonymized', 'hrm_candidate', $candidate->id, [], ['status' => 'anonymized'], 'Configured retention period expired');
                }
            });
        }

        if (Schema::hasTable('hrm_learning_enrollments')) {
            DB::table('hrm_learning_enrollments')->when($business, fn ($query) => $query->where('business_id', $business))
                ->where('status', 'completed')->whereDate('certificate_expires_on', '<', $today)->update(['status' => 'expired', 'updated_at' => now()]);
        }
        if (Schema::hasTable('hrm_engagement_surveys')) {
            DB::table('hrm_engagement_surveys')->when($business, fn ($query) => $query->where('business_id', $business))
                ->where('status', 'open')->whereDate('closes_on', '<', $today)->update(['status' => 'closed', 'updated_at' => now()]);
        }

        return self::SUCCESS;
    }
}
