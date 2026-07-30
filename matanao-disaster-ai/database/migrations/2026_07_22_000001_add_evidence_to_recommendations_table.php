<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recommendations', function (Blueprint $table): void {
            if (! Schema::hasColumn('recommendations', 'basis')) {
                $table->text('basis')->nullable()->after('medicine_kits');
            }

            if (! Schema::hasColumn('recommendations', 'source')) {
                $table->string('source', 50)->default('decision_tree')->after('basis');
            }

            if (! Schema::hasColumn('recommendations', 'input_snapshot')) {
                $table->json('input_snapshot')->nullable()->after('source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recommendations', function (Blueprint $table): void {
            $columns = collect(['basis', 'source', 'input_snapshot'])
                ->filter(fn (string $column): bool => Schema::hasColumn('recommendations', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
