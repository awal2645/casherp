<?php

namespace App\Console\Commands;

use App\BusinessClosureRequest;
use App\Notifications\BusinessClosureNotification;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FinalizeScheduledBusinessClosures extends Command
{
    protected $signature = 'casherp:finalize-company-closures {--dry-run}';

    protected $description = 'Deactivate companies whose audited recovery window has ended.';

    public function handle(): int
    {
        $closures = BusinessClosureRequest::where('status', 'scheduled')
            ->where('scheduled_for', '<=', now())
            ->with('business')
            ->get();

        foreach ($closures as $closure) {
            $this->line($closure->business_id.' - '.optional($closure->business)->name);
            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($closure) {
                if ($closure->business) {
                    $closure->business->update(['is_active' => false]);
                    if (Schema::hasTable('subscriptions')) {
                        DB::table('subscriptions')
                            ->where('business_id', $closure->business_id)
                            ->where('status', 'approved')
                            ->update(['end_date' => now()->toDateString(), 'updated_at' => now()]);
                    }
                    if (Schema::hasTable('business_document_shares') && Schema::hasTable('business_documents')) {
                        DB::table('business_document_shares')
                            ->whereIn('business_document_id', function ($query) use ($closure) {
                                $query->select('id')->from('business_documents')
                                    ->where('business_id', $closure->business_id);
                            })
                            ->whereNull('revoked_at')
                            ->update(['revoked_at' => now(), 'updated_at' => now()]);
                    }
                }

                $closure->update(['status' => 'closed', 'closed_at' => now()]);
            });

            try {
                $closure->refresh()->loadMissing('business');
                $userIds = collect([
                    $closure->requested_by,
                    optional($closure->business)->owner_id,
                ])->filter()->unique()->values();
                User::whereIn('id', $userIds)->get()->each(function (User $user) use ($closure) {
                    $user->notify(new BusinessClosureNotification($closure, 'closed'));
                });
            } catch (\Throwable $exception) {
                Log::warning('Unable to create final company closure notification.', [
                    'closure_id' => $closure->id,
                    'exception' => get_class($exception),
                ]);
            }
        }

        $this->info($this->option('dry-run')
            ? $closures->count().' closure(s) are due.'
            : $closures->count().' closure(s) finalised.');

        return self::SUCCESS;
    }
}
