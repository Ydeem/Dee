<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_structures')) {
            return;
        }

        $rows = DB::table('salary_structures')->get();

        Schema::disableForeignKeyConstraints();

        Schema::drop('salary_structures');

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('housing_allowance', 12, 2)->default(0);
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->decimal('meal_allowance', 12, 2)->default(0);
            $table->decimal('other_allowances', 12, 2)->default(0);
            $table->decimal('ssnit_employee', 5, 2)->default(5.5);
            $table->decimal('ssnit_employer', 5, 2)->default(13.0);
            $table->decimal('income_tax_rate', 5, 2)->default(0);
            $table->string('status')->default('Active');
            // Legacy columns preserved for backward compatibility.
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->json('allowances')->nullable();
            $table->date('effective_date')->nullable();
            $table->string('currency')->nullable();
            $table->string('pay_frequency')->nullable();
            $table->timestamps();
        });

        foreach ($rows as $row) {
            $name = $row->name ?? ('Structure ' . $row->id);
            $allowances = json_decode((string) ($row->allowances ?? ''), true);
            if (! is_array($allowances)) {
                $allowances = [];
            }

            $housing = (float) ($row->housing_allowance ?? 0);
            $transport = (float) ($row->transport_allowance ?? 0);
            $meal = (float) ($row->meal_allowance ?? 0);
            $other = (float) ($row->other_allowances ?? 0);

            foreach ($allowances as $item) {
                $label = strtolower((string) ($item['name'] ?? ''));
                $amount = (float) ($item['amount'] ?? 0);
                if (str_contains($label, 'housing')) {
                    $housing += $amount;
                } elseif (str_contains($label, 'transport')) {
                    $transport += $amount;
                } elseif (str_contains($label, 'meal') || str_contains($label, 'food')) {
                    $meal += $amount;
                } else {
                    $other += $amount;
                }
            }

            DB::table('salary_structures')->insert([
                'id' => $row->id,
                'name' => $name,
                'basic_salary' => $row->basic_salary ?? 0,
                'housing_allowance' => $housing,
                'transport_allowance' => $transport,
                'meal_allowance' => $meal,
                'other_allowances' => $other,
                'ssnit_employee' => $row->ssnit_employee ?? 5.5,
                'ssnit_employer' => $row->ssnit_employer ?? 13.0,
                'income_tax_rate' => $row->income_tax_rate ?? 0,
                'status' => $row->status ?? 'Active',
                'employee_id' => $row->employee_id ?? null,
                'allowances' => json_encode($allowances),
                'effective_date' => $row->effective_date ?? null,
                'currency' => $row->currency ?? 'GHS',
                'pay_frequency' => $row->pay_frequency ?? 'Monthly',
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // No rollback for rebuild migration.
    }
};

