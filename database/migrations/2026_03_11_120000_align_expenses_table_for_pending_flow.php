<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expenses')) {
            return;
        }

        $hasReceipt = Schema::hasColumn('expenses', 'receipt');
        $hasRejectedBy = Schema::hasColumn('expenses', 'rejected_by');

        Schema::table('expenses', function (Blueprint $table) use ($hasReceipt, $hasRejectedBy) {
            if (!$hasReceipt) {
                $table->string('receipt')->nullable()->after('description');
            }

            if (!$hasRejectedBy) {
                $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('employees')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('expenses', 'receipt_path') && Schema::hasColumn('expenses', 'receipt')) {
            DB::table('expenses')
                ->whereNull('receipt')
                ->whereNotNull('receipt_path')
                ->orderBy('id')
                ->select('id', 'receipt_path')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('expenses')
                            ->where('id', $row->id)
                            ->update(['receipt' => $row->receipt_path]);
                    }
                });
        }

        if (Schema::hasColumn('expenses', 'status')) {
            try {
                Schema::table('expenses', function (Blueprint $table) {
                    $table->string('status')->default('Pending')->change();
                });
            } catch (\Throwable $e) {
                // Keep migration resilient across drivers where enum->string alter is unsupported.
            }

            try {
                DB::table('expenses')
                    ->whereIn('status', ['Draft', 'Submitted', 'Under Review'])
                    ->update(['status' => 'Pending']);
            } catch (\Throwable $e) {
                // If enum conversion was unsupported, keep legacy values unchanged.
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('expenses')) {
            return;
        }

        $hasRejectedBy = Schema::hasColumn('expenses', 'rejected_by');
        $hasReceipt = Schema::hasColumn('expenses', 'receipt');

        Schema::table('expenses', function (Blueprint $table) use ($hasRejectedBy, $hasReceipt) {
            if ($hasRejectedBy) {
                $table->dropConstrainedForeignId('rejected_by');
            }

            if ($hasReceipt) {
                $table->dropColumn('receipt');
            }
        });
    }
};
