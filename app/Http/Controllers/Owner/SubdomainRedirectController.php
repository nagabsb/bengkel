<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubdomainRedirectController extends Controller
{
    public function redirect(Request $request, string $path = ''): RedirectResponse
    {
        $tenantId = trim((string) $request->attributes->get('tenant_id', ''));
        if ($tenantId === '') {
            abort(404, 'Tenant tidak ditemukan.');
        }

        $normalizedPath = trim($path, '/');
        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            abort(404, 'Halaman owner tidak ditemukan.');
        }

        if (str_starts_with($normalizedPath, $tenantId.'/')) {
            abort(404, 'Halaman owner tidak ditemukan.');
        }

        $targetPath = "/owner/{$tenantId}/{$normalizedPath}";
        $queryString = trim((string) $request->getQueryString());

        if ($queryString !== '') {
            $targetPath .= '?'.$queryString;
        }

        return redirect()->to($targetPath);
    }
}
