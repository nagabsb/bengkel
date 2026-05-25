<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\AiAgent\SetDefaultPlatformAiAgentRequest;
use App\Http\Requests\Platform\AiAgent\StorePlatformAiAgentRequest;
use App\Http\Requests\Platform\AiAgent\TestPlatformAiPromptOutputRequest;
use App\Http\Requests\Platform\AiAgent\TestPlatformAiAgentSettingRequest;
use App\Http\Requests\Platform\AiAgent\TogglePlatformAiAgentStatusRequest;
use App\Http\Requests\Platform\AiAgent\UpdatePlatformAiAgentRequest;
use App\Http\Requests\Platform\AiAgent\UpdatePlatformAiPromptSettingRequest;
use App\Services\Platform\PlatformAiAgentService;
use App\Services\Platform\PlatformAiPromptSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAgentSettingController extends Controller
{
    public function index(
        Request $request,
        PlatformAiAgentService $platformAiAgentService,
        PlatformAiPromptSettingService $platformAiPromptSettingService,
    ): Response
    {
        $pageData = [
            ...$platformAiAgentService->buildPageData($request),
            ...$platformAiPromptSettingService->buildPageData(),
        ];

        return Inertia::render('Platform/AiAgentSettings', [
            'user' => $request->user()?->only('name', 'email'),
            ...$pageData,
        ]);
    }

    public function store(
        StorePlatformAiAgentRequest $request,
        PlatformAiAgentService $platformAiAgentService,
    ): RedirectResponse {
        $platformAiAgentService->createAgent($request->validated());

        return back()->with(['status' => 'Agent AI berhasil ditambahkan.', 'status_level' => 'success']);
    }

    public function update(
        UpdatePlatformAiAgentRequest $request,
        int $agent,
        PlatformAiAgentService $platformAiAgentService,
    ): RedirectResponse {
        $platformAiAgentService->updateAgent($agent, $request->validated());

        return back()->with(['status' => 'Agent AI berhasil diperbarui.', 'status_level' => 'success']);
    }

    public function updateStatus(
        TogglePlatformAiAgentStatusRequest $request,
        int $agent,
        PlatformAiAgentService $platformAiAgentService,
    ): RedirectResponse {
        $isActive = (bool) $request->validated('is_active');
        $platformAiAgentService->updateAgentStatus($agent, $isActive);

        return back()->with([
            'status' => $isActive
                ? 'Agent AI berhasil diaktifkan.'
                : 'Agent AI berhasil dinonaktifkan.',
            'status_level' => 'success',
        ]);
    }

    public function setDefault(
        SetDefaultPlatformAiAgentRequest $request,
        int $agent,
        PlatformAiAgentService $platformAiAgentService,
    ): RedirectResponse {
        $platformAiAgentService->setDefaultAgent($agent);

        return back()->with(['status' => 'Default agent AI berhasil diperbarui.', 'status_level' => 'success']);
    }

    public function destroy(int $agent, PlatformAiAgentService $platformAiAgentService): RedirectResponse
    {
        $platformAiAgentService->deleteAgent($agent);

        return back()->with(['status' => 'Agent AI berhasil dihapus.', 'status_level' => 'success']);
    }

    public function test(
        TestPlatformAiAgentSettingRequest $request,
        int $agent,
        PlatformAiAgentService $platformAiAgentService,
    ): RedirectResponse {
        $result = $platformAiAgentService->testConnection($agent, [
            ...$request->validated(),
            'actor_user_id' => (string) ($request->user()?->getAuthIdentifier() ?? ''),
        ]);

        return back()->with([
            'status' => $result['message'],
            'status_level' => $result['ok'] ? 'success' : 'error',
        ]);
    }

    public function updatePrompt(
        UpdatePlatformAiPromptSettingRequest $request,
        string $feature,
        PlatformAiPromptSettingService $platformAiPromptSettingService,
    ): RedirectResponse {
        $platformAiPromptSettingService->updatePromptSetting(
            $feature,
            $request->validated(),
            $request->user(),
        );

        return back()->with([
            'status' => 'Pengaturan prompt AI berhasil diperbarui.',
            'status_level' => 'success',
        ]);
    }

    public function testPromptOutput(
        TestPlatformAiPromptOutputRequest $request,
        string $feature,
        PlatformAiPromptSettingService $platformAiPromptSettingService,
    ): RedirectResponse {
        $result = $platformAiPromptSettingService->testPromptOutput(
            $feature,
            $request->validated(),
            $request->user(),
        );

        return back()->with([
            'status' => $result['message'],
            'status_level' => $result['ok'] ? 'success' : 'error',
            'ai_prompt_test_result' => $result['result'],
        ]);
    }
}
