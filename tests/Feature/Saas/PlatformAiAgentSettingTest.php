<?php

use App\Models\PlatformAiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('superadmin can create ai agent setting', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $this->actingAs($user)
        ->post('/platform/settings/ai-agent', [
            'name' => 'OpenAI Primary',
            'provider' => 'openai',
            'agent_model' => 'gpt-5-mini',
            'api_key' => 'sk-test-1234567890',
            'priority_order' => 10,
            'is_active' => true,
            'is_default' => true,
            'is_failover_enabled' => true,
            'monthly_token_limit' => 100000,
        ])
        ->assertRedirect();

    $setting = PlatformAiSetting::query()->first();

    expect($setting)->not->toBeNull();
    expect($setting?->name)->toBe('OpenAI - GPT-5-MINI');
    expect($setting?->provider)->toBe('openai');
    expect($setting?->agent_model)->toBe('gpt-5-mini');
    expect($setting?->is_default)->toBeTrue();
    expect($setting?->is_active)->toBeTrue();
});

test('superadmin can update ai agent and switch default', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $primary = PlatformAiSetting::query()->create([
        'name' => 'Primary',
        'provider' => 'openai',
        'agent_model' => 'gpt-5-mini',
        'api_key' => 'sk-test-primary-123456',
        'is_active' => true,
        'is_default' => true,
        'is_failover_enabled' => true,
        'priority_order' => 10,
    ]);

    $fallback = PlatformAiSetting::query()->create([
        'name' => 'Fallback',
        'provider' => 'gemini',
        'agent_model' => 'gemini-2.5-flash-lite',
        'api_key' => 'sk-test-fallback-123456',
        'is_active' => true,
        'is_default' => false,
        'is_failover_enabled' => true,
        'priority_order' => 20,
    ]);

    $this->actingAs($user)
        ->patch("/platform/settings/ai-agent/{$fallback->id}", [
            'name' => 'Gemini Fallback',
            'provider' => 'gemini',
            'agent_model' => 'gemini-3.1-flash',
            'priority_order' => 5,
            'is_active' => true,
            'is_default' => true,
            'is_failover_enabled' => true,
        ])
        ->assertRedirect();

    $primary->refresh();
    $fallback->refresh();

    expect($primary->is_default)->toBeFalse();
    expect($fallback->is_default)->toBeTrue();
    expect($fallback->name)->toBe('Google Gemini - GEMINI-3.1-FLASH');
    expect($fallback->agent_model)->toBe('gemini-3.1-flash');
});

test('superadmin can run ai api key test for selected agent', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $agent = PlatformAiSetting::query()->create([
        'name' => 'OpenAI Primary',
        'provider' => 'openai',
        'agent_model' => 'gpt-5-mini',
        'api_key' => 'sk-test-1234567890',
        'is_active' => true,
        'is_default' => true,
        'is_failover_enabled' => true,
        'priority_order' => 10,
    ]);

    Ai::fakeAgent(AnonymousAgent::class, ['OK']);

    $this->actingAs($user)
        ->post("/platform/settings/ai-agent/{$agent->id}/test", [
            'api_key' => null,
        ])
        ->assertRedirect();

    $agent->refresh();

    expect($agent->test_success_count)->toBe(1);
    expect($agent->test_failed_count)->toBe(0);
    expect($agent->last_test_status)->toBe('success');
    expect($agent->last_test_message)->toBe('Test API key berhasil dijalankan.');
});

test('deleting default agent assigns next default agent', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $primary = PlatformAiSetting::query()->create([
        'name' => 'Primary',
        'provider' => 'openai',
        'agent_model' => 'gpt-5-mini',
        'api_key' => 'sk-test-primary-123456',
        'is_active' => true,
        'is_default' => true,
        'is_failover_enabled' => true,
        'priority_order' => 10,
    ]);

    $secondary = PlatformAiSetting::query()->create([
        'name' => 'Secondary',
        'provider' => 'gemini',
        'agent_model' => 'gemini-2.5-flash-lite',
        'api_key' => 'sk-test-secondary-123456',
        'is_active' => true,
        'is_default' => false,
        'is_failover_enabled' => true,
        'priority_order' => 20,
    ]);

    $this->actingAs($user)
        ->delete("/platform/settings/ai-agent/{$primary->id}")
        ->assertRedirect();

    expect(PlatformAiSetting::query()->where('id', $primary->id)->exists())->toBeFalse();

    $secondary->refresh();
    expect($secondary->is_default)->toBeTrue();
});


test('duplicate provider and model gets automatic name suffix', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $this->actingAs($user)
        ->post('/platform/settings/ai-agent', [
            'provider' => 'openai',
            'agent_model' => 'gpt-5-mini',
            'api_key' => 'sk-test-primary-same-model-123456',
            'priority_order' => 1,
            'is_active' => true,
            'is_default' => true,
            'is_failover_enabled' => true,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post('/platform/settings/ai-agent', [
            'provider' => 'openai',
            'agent_model' => 'gpt-5-mini',
            'api_key' => 'sk-test-secondary-same-model-654321',
            'priority_order' => 2,
            'is_active' => true,
            'is_default' => false,
            'is_failover_enabled' => true,
        ])
        ->assertRedirect();

    $first = PlatformAiSetting::query()->orderBy('id')->first();
    $second = PlatformAiSetting::query()->orderBy('id')->skip(1)->first();

    expect($first?->name)->toBe('OpenAI - GPT-5-MINI');
    expect($second?->name)->toBe('OpenAI - GPT-5-MINI - 2');
});

test('duplicate api key is rejected when creating a new agent', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $this->actingAs($user)
        ->post('/platform/settings/ai-agent', [
            'provider' => 'openai',
            'agent_model' => 'gpt-5-mini',
            'api_key' => 'sk-test-duplicate-key-123456',
            'priority_order' => 1,
            'is_active' => true,
            'is_default' => true,
            'is_failover_enabled' => true,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->from('/platform/settings/ai-agent')
        ->post('/platform/settings/ai-agent', [
            'provider' => 'gemini',
            'agent_model' => 'gemini-2.5-flash-lite',
            'api_key' => 'sk-test-duplicate-key-123456',
            'priority_order' => 2,
            'is_active' => true,
            'is_default' => false,
            'is_failover_enabled' => true,
        ])
        ->assertRedirect('/platform/settings/ai-agent')
        ->assertSessionHasErrors(['api_key']);

    expect(PlatformAiSetting::query()->count())->toBe(1);
});

test('duplicate api key is rejected when updating agent', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $first = PlatformAiSetting::query()->create([
        'name' => 'OpenAI - GPT-5-MINI',
        'provider' => 'openai',
        'agent_model' => 'gpt-5-mini',
        'api_key' => 'sk-test-update-duplicate-111111',
        'is_active' => true,
        'is_default' => true,
        'is_failover_enabled' => true,
        'priority_order' => 1,
    ]);

    $second = PlatformAiSetting::query()->create([
        'name' => 'Google Gemini - GEMINI-2.5-FLASH-LITE',
        'provider' => 'gemini',
        'agent_model' => 'gemini-2.5-flash-lite',
        'api_key' => 'sk-test-update-duplicate-222222',
        'is_active' => true,
        'is_default' => false,
        'is_failover_enabled' => true,
        'priority_order' => 2,
    ]);

    $this->actingAs($user)
        ->from('/platform/settings/ai-agent')
        ->patch("/platform/settings/ai-agent/{$second->id}", [
            'provider' => 'gemini',
            'agent_model' => 'gemini-2.5-flash-lite',
            'api_key' => 'sk-test-update-duplicate-111111',
            'priority_order' => 2,
            'is_active' => true,
            'is_default' => false,
            'is_failover_enabled' => true,
        ])
        ->assertRedirect('/platform/settings/ai-agent')
        ->assertSessionHasErrors(['api_key']);

    $first->refresh();
    $second->refresh();

    expect($first->api_key)->toBe('sk-test-update-duplicate-111111');
    expect($second->api_key)->toBe('sk-test-update-duplicate-222222');
});
test('non privileged user cannot manage ai agents', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/platform/settings/ai-agent', [
            'name' => 'Blocked Agent',
            'provider' => 'openai',
            'agent_model' => 'gpt-5-mini',
        ])
        ->assertForbidden();
});

