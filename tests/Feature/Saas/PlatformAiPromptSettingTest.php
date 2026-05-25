<?php

use App\Models\PlatformAiPromptSetting;
use App\Models\PlatformAiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('superadmin can update ai prompt setting by feature category', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $this->actingAs($user)
        ->patch('/platform/settings/ai-agent/prompts/service_estimate_v1', [
            'system_prompt' => 'System prompt test update service estimate.',
            'feature_prompt' => 'Feature prompt test update service estimate.',
            'is_active' => true,
        ])
        ->assertRedirect();

    $setting = PlatformAiPromptSetting::query()
        ->where('feature_key', 'service_estimate_v1')
        ->first();

    expect($setting)->not->toBeNull();
    expect($setting?->system_prompt)->toBe('System prompt test update service estimate.');
    expect($setting?->feature_prompt)->toBe('Feature prompt test update service estimate.');
    expect($setting?->is_active)->toBeTrue();
});

test('superadmin can update ai prompt setting using structured feature prompt config', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $this->actingAs($user)
        ->patch('/platform/settings/ai-agent/prompts/service_estimate_v1', [
            'system_prompt' => 'System prompt builder mode.',
            'feature_prompt_config' => [
                'max_items' => 4,
                'prioritize_safety' => false,
                'include_confidence' => true,
                'include_risk_notes' => true,
                'include_advice' => false,
                'include_item_reason' => true,
                'include_disclaimer' => true,
                'disclaimer_text' => 'Estimasi ini perlu konfirmasi final teknisi.',
                'review_focus' => 'Utamakan biaya yang paling mudah dipahami customer.',
            ],
            'is_active' => true,
        ])
        ->assertRedirect();

    $setting = PlatformAiPromptSetting::query()
        ->where('feature_key', 'service_estimate_v1')
        ->first();

    expect($setting)->not->toBeNull();
    expect($setting?->system_prompt)->toBe('System prompt builder mode.');
    expect($setting?->feature_prompt_config)->toMatchArray([
        'max_items' => 4,
        'prioritize_safety' => false,
        'include_confidence' => true,
        'include_advice' => false,
        'disclaimer_text' => 'Estimasi ini perlu konfirmasi final teknisi.',
    ]);
    expect($setting?->feature_prompt)->toContain('Maksimal 4 item');
    expect($setting?->feature_prompt)->toContain('Utamakan biaya yang paling mudah dipahami customer.');
    expect($setting?->feature_prompt)->toContain('Estimasi ini perlu konfirmasi final teknisi.');
});

test('superadmin can update diagnosis prompt category using structured feature prompt config', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $this->actingAs($user)
        ->patch('/platform/settings/ai-agent/prompts/symptom_diagnosis_v1', [
            'system_prompt' => 'System prompt diagnosis builder mode.',
            'feature_prompt_config' => [
                'max_possible_causes' => 2,
                'prioritize_safety_risk' => true,
                'include_confidence' => true,
                'include_recommended_checks' => true,
                'include_recommended_actions' => false,
                'include_warnings' => true,
                'include_customer_advice' => true,
                'include_disclaimer' => true,
                'disclaimer_text' => 'Diagnosa awal wajib diverifikasi teknisi.',
                'diagnosis_focus' => 'Utamakan penyebab yang paling mungkin sesuai pola gejala.',
            ],
            'is_active' => true,
        ])
        ->assertRedirect();

    $setting = PlatformAiPromptSetting::query()
        ->where('feature_key', 'symptom_diagnosis_v1')
        ->first();

    expect($setting)->not->toBeNull();
    expect($setting?->system_prompt)->toBe('System prompt diagnosis builder mode.');
    expect($setting?->feature_prompt_config)->toMatchArray([
        'max_possible_causes' => 2,
        'prioritize_safety_risk' => true,
        'include_recommended_actions' => false,
        'disclaimer_text' => 'Diagnosa awal wajib diverifikasi teknisi.',
    ]);
    expect($setting?->feature_prompt)->toContain('Maksimal 2 dugaan penyebab');
    expect($setting?->feature_prompt)->toContain('Utamakan penyebab yang paling mungkin sesuai pola gejala.');
    expect($setting?->feature_prompt)->toContain('Diagnosa awal wajib diverifikasi teknisi.');
});

test('superadmin can update monthly business report prompt category using structured feature prompt config', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $this->actingAs($user)
        ->patch('/platform/settings/ai-agent/prompts/monthly_business_report_v1', [
            'system_prompt' => 'System prompt laporan bulanan builder mode.',
            'feature_prompt_config' => [
                'max_highlights' => 4,
                'include_financial_summary' => true,
                'include_operational_summary' => true,
                'include_risks' => true,
                'include_recommendations' => false,
                'include_next_month_focus' => true,
                'include_disclaimer' => true,
                'disclaimer_text' => 'Laporan AI wajib diverifikasi owner sebelum dipakai keputusan final.',
                'report_focus' => 'Fokus pada tren omzet, produktivitas order servis, dan prioritas aksi bulan depan.',
            ],
            'is_active' => true,
        ])
        ->assertRedirect();

    $setting = PlatformAiPromptSetting::query()
        ->where('feature_key', 'monthly_business_report_v1')
        ->first();

    expect($setting)->not->toBeNull();
    expect($setting?->system_prompt)->toBe('System prompt laporan bulanan builder mode.');
    expect($setting?->feature_prompt_config)->toMatchArray([
        'max_highlights' => 4,
        'include_financial_summary' => true,
        'include_recommendations' => false,
        'disclaimer_text' => 'Laporan AI wajib diverifikasi owner sebelum dipakai keputusan final.',
    ]);
    expect($setting?->feature_prompt)->toContain('Maksimal 4 poin highlights');
    expect($setting?->feature_prompt)->toContain('Fokus pada tren omzet, produktivitas order servis, dan prioritas aksi bulan depan.');
    expect($setting?->feature_prompt)->toContain('Laporan AI wajib diverifikasi owner sebelum dipakai keputusan final.');
});

test('non privileged user cannot update ai prompt setting', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/platform/settings/ai-agent/prompts/service_estimate_v1', [
            'system_prompt' => 'Blocked update',
            'feature_prompt' => 'Blocked update',
        ])
        ->assertForbidden();
});

test('superadmin can test ai prompt output', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    PlatformAiSetting::query()->create([
        'name' => 'OpenAI - GPT-5-MINI',
        'provider' => 'openai',
        'agent_model' => 'gpt-5-mini',
        'api_key' => 'sk-test-platform-ai-prompt-output-123456',
        'is_active' => true,
        'is_default' => true,
        'is_failover_enabled' => true,
        'priority_order' => 1,
    ]);

    Ai::fakeAgent(AnonymousAgent::class, ['{"items":[{"item_type":"service","label":"Tune Up","qty":1,"unit_price":350000}]}']);

    $this->actingAs($user)
        ->post('/platform/settings/ai-agent/prompts/service_estimate_v1/test-output', [
            'test_input' => '{"order":{"complaint":"Mesin brebet"}}',
        ])
        ->assertRedirect()
        ->assertSessionHas('ai_prompt_test_result');
});
