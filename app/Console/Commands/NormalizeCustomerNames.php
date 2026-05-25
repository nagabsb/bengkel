<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

class NormalizeCustomerNames extends Command
{
    protected $signature = 'customers:normalize-names {--dry-run : Lihat jumlah perubahan tanpa menyimpan}';

    protected $description = 'Normalisasi nama customer dengan menghapus gelar/title di depan nama.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $processed = 0;
        $updated = 0;
        $samples = [];

        Customer::query()
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use (&$processed, &$updated, &$samples, $isDryRun): void {
                foreach ($customers as $customer) {
                    $processed++;
                    $originalName = trim((string) $customer->name);
                    $normalizedName = $this->normalizeName($originalName);

                    if ($normalizedName === '' || $normalizedName === $originalName) {
                        continue;
                    }

                    if (! $isDryRun) {
                        $customer->forceFill(['name' => $normalizedName])->save();
                    }

                    $updated++;

                    if (count($samples) < 10) {
                        $samples[] = [$originalName, $normalizedName];
                    }
                }
            }, column: 'id');

        $this->info($isDryRun ? 'Dry-run selesai.' : 'Normalisasi nama customer selesai.');
        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Total diproses', (string) $processed],
                ['Nama diubah', (string) $updated],
                ['Mode', $isDryRun ? 'Dry-run' : 'Simpan'],
            ],
        );

        if ($samples !== []) {
            $this->table(
                ['Sebelum', 'Sesudah'],
                $samples,
            );
        }

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($name));
        if (! is_string($normalized) || $normalized === '') {
            return '';
        }

        $prefixPattern = '/^(?:(?:bpk|bapak|bp|ibu|sdr|sdri|saudara|drg|dr|h|hj|ir|prof|ust|ustadz|ustad|kh|tn|ny|mr|mrs|ms)\.?\s+)+/iu';
        $normalized = preg_replace($prefixPattern, '', $normalized);
        if (! is_string($normalized)) {
            return '';
        }

        $normalized = $this->removeAcademicSuffixes($normalized);
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));
        if (! is_string($normalized)) {
            return '';
        }

        return $normalized !== '' ? $normalized : '';
    }

    private function removeAcademicSuffixes(string $name): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($name));
        if (! is_string($normalized) || $normalized === '') {
            return '';
        }

        $tokens = preg_split('/\s+/', $normalized) ?: [];
        if ($tokens === []) {
            return $normalized;
        }

        while (count($tokens) > 1) {
            $lastToken = trim((string) end($tokens), ",");
            if ($lastToken === '') {
                array_pop($tokens);
                continue;
            }

            if (! $this->looksLikeAcademicSuffixToken($lastToken)) {
                break;
            }

            array_pop($tokens);
        }

        $cleanTokens = array_values(array_filter(
            array_map(
                static fn (string $token): string => trim($token, ","),
                $tokens,
            ),
            static fn (string $token): bool => $token !== '',
        ));

        return trim(implode(' ', $cleanTokens));
    }

    private function looksLikeAcademicSuffixToken(string $token): bool
    {
        $normalized = strtolower(trim($token, " \t\n\r\0\x0B,."));
        if ($normalized === '') {
            return false;
        }

        // Format bertitik: S.Kom, S.I.Kom, M.TI, M.Farm, S.Psi, dsb.
        if (preg_match('/^[a-z]{1,4}(?:\.[a-z]{1,8})+$/i', rtrim($normalized, '.')) === 1) {
            return true;
        }

        $knownNoDotTokens = [
            'sos',
            'kom',
            'si',
            'se',
            'st',
            'sh',
            'psi',
            'farm',
            'gz',
            'pd',
            'ak',
            'mm',
            'ti',
            'ip',
            'amd',
            'd3',
        ];

        return in_array(str_replace('.', '', $normalized), $knownNoDotTokens, true);
    }
}
