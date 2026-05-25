<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        if (! Schema::hasColumn('tenants', 'subdomain')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->string('subdomain', 63)->nullable()->after('code');
            });
        }

        $this->backfillSubdomains();
        $this->safeSchema('tenants', fn (Blueprint $table) => $table->unique('subdomain', 'tenants_subdomain_unique'));
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'subdomain')) {
            return;
        }

        $this->safeSchema('tenants', fn (Blueprint $table) => $table->dropUnique('tenants_subdomain_unique'));
        $this->safeSchema('tenants', fn (Blueprint $table) => $table->dropColumn('subdomain'));
    }

    private function backfillSubdomains(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'subdomain')) {
            return;
        }

        $tenantModel = $this->tenantModel();
        $usedSubdomains = [];

        $tenantModel->newQuery()
            ->orderBy('id')
            ->get(['id', 'name', 'code', 'subdomain'])
            ->each(function (Model $tenant) use ($tenantModel, &$usedSubdomains): void {
                $currentSubdomain = trim((string) $tenant->getAttribute('subdomain'));
                $source = $currentSubdomain !== ''
                    ? $currentSubdomain
                    : trim((string) $tenant->getAttribute('name'));

                if ($source === '') {
                    $source = trim((string) $tenant->getAttribute('code'));
                }

                if ($source === '') {
                    $source = 'tenant';
                }

                $candidate = $this->makeUniqueSubdomain(
                    $this->normalizeSubdomain($source),
                    $usedSubdomains,
                );

                $usedSubdomains[$candidate] = true;

                if ($candidate === $currentSubdomain) {
                    return;
                }

                $tenantModel->newQuery()
                    ->where('id', (string) $tenant->getAttribute('id'))
                    ->update([
                        'subdomain' => $candidate,
                        'updated_at' => now(),
                    ]);
            });
    }

    /**
     * @param  array<string, bool>  $usedSubdomains
     */
    private function makeUniqueSubdomain(string $baseSubdomain, array $usedSubdomains): string
    {
        if (! array_key_exists($baseSubdomain, $usedSubdomains)) {
            return $baseSubdomain;
        }

        $counter = 2;

        while ($counter <= 9999) {
            $suffix = '-'.$counter;
            $baseLimit = max(1, 63 - strlen($suffix));
            $candidateBase = trim(Str::limit($baseSubdomain, $baseLimit, ''), '-');
            $candidateBase = $candidateBase !== '' ? $candidateBase : 'tenant';
            $candidate = "{$candidateBase}{$suffix}";

            if (! array_key_exists($candidate, $usedSubdomains)) {
                return $candidate;
            }

            $counter++;
        }

        $randomSuffix = '-'.Str::lower(Str::random(6));
        $baseLimit = max(1, 63 - strlen($randomSuffix));
        $candidateBase = trim(Str::limit($baseSubdomain, $baseLimit, ''), '-');
        $candidateBase = $candidateBase !== '' ? $candidateBase : 'tenant';

        return "{$candidateBase}{$randomSuffix}";
    }

    private function normalizeSubdomain(string $value): string
    {
        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->toString();

        $limited = trim(Str::limit($normalized !== '' ? $normalized : 'tenant', 63, ''), '-');

        return $limited !== '' ? $limited : 'tenant';
    }

    private function tenantModel(): Model
    {
        return new class extends Model {
            protected $table = 'tenants';

            public $incrementing = false;

            protected $keyType = 'string';

            protected $guarded = [];
        };
    }

    private function safeSchema(string $table, callable $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Throwable) {
            // Keep migration idempotent across local schemas.
        }
    }
};
