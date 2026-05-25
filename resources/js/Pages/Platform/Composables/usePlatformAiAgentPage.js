import { computed, ref, watch } from 'vue';
import { useForm, usePage, useRemember } from '@inertiajs/vue3';
import { fetchPlatformAiAgents } from '../Services/platformAiAgentService';
import {
    buildFeaturePromptFromConfig,
    buildTestInputFromConfig,
    getFeaturePromptSchema,
    getTestInputSchema,
    normalizeFeaturePromptConfig,
    normalizeTestInputConfig,
    resolveTestInputConfigFromTemplate,
} from '../Services/platformAiPromptBuilder';

export const usePlatformAiAgentPage = (props) => {
    const page = usePage();
    const logoutForm = useForm({});
    const agentForm = useForm({
        provider: 'openai',
        agent_model: 'gpt-5',
        api_key: '',
        remove_api_key: false,
        priority_order: Number(props.agentSummary?.next_priority_order) || 1,
        monthly_token_limit: null,
        is_active: true,
        is_default: false,
        is_failover_enabled: true,
    });
    const statusForm = useForm({
        is_active: false,
    });
    const defaultForm = useForm({});
    const deleteForm = useForm({});
    const testForm = useForm({
        api_key: null,
    });
    const promptForm = useForm({
        feature_key: '',
        system_prompt: '',
        feature_prompt: '',
        feature_prompt_config: {},
        is_active: true,
    });
    const promptTestForm = useForm({
        feature_key: '',
        test_input: '',
        test_input_config: {},
    });

    const editingAgentId = ref(null);
    const editingApiKeyValue = ref('');
    const isAgentModalOpen = ref(false);
    const showApiKey = ref(false);
    const selectedPromptFeatureKey = useRemember('', 'platform-ai-agent.selected-prompt-feature-key');

    const tableLoading = ref(false);
    const togglingAgentId = ref(null);
    const testingAgentId = ref(null);
    const defaultingAgentId = ref(null);
    const deletingAgentId = ref(null);

    const dashboardPath = '/platform/dashboard';
    const tenantsPath = '/platform/tenants';
    const permissionsPath = '/platform/settings/permissions';
    const menusPath = '/platform/settings/menus';
    const plansPath = '/platform/settings/plans';
    const applicationPath = '/platform/settings/application';
    const paymentsPath = '/platform/settings/payments';
    const vehicleMastersPath = '/platform/settings/vehicle-masters';
    const aiAgentPath = '/platform/settings/ai-agent';

    const aiAgentStorePath = '/platform/settings/ai-agent';
    const aiAgentUpdatePath = (agentId) => `/platform/settings/ai-agent/${agentId}`;
    const aiAgentStatusPath = (agentId) => `/platform/settings/ai-agent/${agentId}/status`;
    const aiAgentDefaultPath = (agentId) => `/platform/settings/ai-agent/${agentId}/default`;
    const aiAgentDeletePath = (agentId) => `/platform/settings/ai-agent/${agentId}`;
    const aiAgentTestPath = (agentId) => `/platform/settings/ai-agent/${agentId}/test`;
    const aiPromptUpdatePath = (featureKey) => `/platform/settings/ai-agent/prompts/${encodeURIComponent(String(featureKey || ''))}`;
    const aiPromptTestPath = (featureKey) => `/platform/settings/ai-agent/prompts/${encodeURIComponent(String(featureKey || ''))}/test-output`;

    const providerApiKeyGuides = Object.freeze({
        openai: {
            label: 'OpenAI',
            url: 'https://platform.openai.com/api-keys',
        },
        anthropic: {
            label: 'Anthropic',
            url: 'https://console.anthropic.com/settings/keys',
        },
        gemini: {
            label: 'Google Gemini',
            url: 'https://aistudio.google.com/app/apikey',
        },
        groq: {
            label: 'Groq',
            url: 'https://console.groq.com/keys',
        },
        mistral: {
            label: 'Mistral',
            url: 'https://console.mistral.ai/api-keys/',
        },
        deepseek: {
            label: 'DeepSeek',
            url: 'https://platform.deepseek.com/api_keys',
        },
        kimi: {
            label: 'Kimi (Moonshot)',
            url: 'https://platform.moonshot.ai/console/api-keys',
        },
    });

    const currentPath = computed(() => String(page.url || '').split('?')[0] || '');

    const activeSettingsKey = computed(() => {
        const path = currentPath.value;
        if (path === permissionsPath) return 'permissions';
        if (path === menusPath) return 'menus';
        if (path === plansPath) return 'plans';
        if (path === applicationPath) return 'application';
        if (path === paymentsPath) return 'payments';
        if (path === vehicleMastersPath) return 'vehicle-masters';
        if (path === aiAgentPath) return 'ai-agent';

        return '';
    });

    const isSettingsActive = computed(() => ['permissions', 'menus', 'application', 'payments', 'vehicle-masters', 'ai-agent'].includes(activeSettingsKey.value));

    const menuItems = computed(() => [
        { key: 'dashboard', label: 'Dasbor', icon: 'dashboard', href: dashboardPath, active: currentPath.value === dashboardPath },
        { key: 'tenants', label: 'Tenant', icon: 'users', href: tenantsPath, active: currentPath.value === tenantsPath },
        { key: 'plans', label: 'Plan', icon: 'billing', href: plansPath, active: activeSettingsKey.value === 'plans' },
        {
            key: 'settings',
            label: 'Pengaturan',
            icon: 'settings',
            active: isSettingsActive.value,
            children: [
                { key: 'permissions', label: 'Permission', href: permissionsPath, active: activeSettingsKey.value === 'permissions' },
                { key: 'menus', label: 'Management Menu', href: menusPath, active: activeSettingsKey.value === 'menus' },
                { key: 'application', label: 'Aplikasi', href: applicationPath, active: activeSettingsKey.value === 'application' },
                { key: 'payments', label: 'Pembayaran', href: paymentsPath, active: activeSettingsKey.value === 'payments' },
                { key: 'vehicle-masters', label: 'Master Kendaraan', href: vehicleMastersPath, active: activeSettingsKey.value === 'vehicle-masters' },
                { key: 'ai-agent', label: 'AI Agent', href: aiAgentPath, active: activeSettingsKey.value === 'ai-agent' },
            ],
        },
    ]);

    const tableFilters = ref({
        search: '',
        sort_by: 'priority_order',
        sort_dir: 'asc',
        per_page: 10,
        cursor: null,
    });

    watch(
        () => props.agentFilters,
        (filters) => {
            tableFilters.value = {
                search: String(filters?.search || ''),
                sort_by: String(filters?.sort_by || 'priority_order'),
                sort_dir: String(filters?.sort_dir || 'asc'),
                per_page: Number(filters?.per_page) || 10,
                cursor: filters?.cursor ? String(filters.cursor) : null,
            };
        },
        {
            immediate: true,
            deep: true,
        },
    );

    const flashStatus = computed(() => String(page.props?.flash?.status || ''));
    const flashStatusLevel = computed(() => {
        const value = String(page.props?.flash?.status_level || 'success').trim().toLowerCase();
        return value === 'error' ? 'error' : 'success';
    });
    const promptTestResult = computed(() => {
        const payload = page.props?.flash?.ai_prompt_test_result;
        return payload && typeof payload === 'object' ? payload : null;
    });
    const pageErrors = computed(() => page.props?.errors || {});
    const nextPriorityOrder = computed(() => {
        const raw = Number(props.agentSummary?.next_priority_order ?? 1);
        return Number.isFinite(raw) && raw > 0 ? Math.trunc(raw) : 1;
    });

    const agentError = computed(() => {
        return String(
            agentForm.errors?.create_agent
            || agentForm.errors?.update_agent
            || statusForm.errors?.status_agent
            || defaultForm.errors?.default_agent
            || deleteForm.errors?.delete_agent
            || testForm.errors?.test_agent
            || pageErrors.value?.create_agent
            || pageErrors.value?.update_agent
            || pageErrors.value?.status_agent
            || pageErrors.value?.default_agent
            || pageErrors.value?.delete_agent
            || pageErrors.value?.test_agent
            || '',
        );
    });

    const normalizedPromptSettings = computed(() => {
        if (!Array.isArray(props.promptSettings)) {
            return [];
        }

        return props.promptSettings
            .map((setting) => ({
                feature_key: String(setting?.feature_key || ''),
                name: String(setting?.name || ''),
                description: String(setting?.description || ''),
                test_input_template: String(setting?.test_input_template || ''),
                system_prompt: String(setting?.system_prompt || ''),
                feature_prompt: String(setting?.feature_prompt || ''),
                feature_prompt_config: setting?.feature_prompt_config && typeof setting.feature_prompt_config === 'object'
                    ? { ...setting.feature_prompt_config }
                    : {},
                has_feature_prompt_config: Boolean(setting?.has_feature_prompt_config ?? true),
                is_active: Boolean(setting?.is_active ?? true),
                updated_at: setting?.updated_at || null,
            }))
            .filter((setting) => setting.feature_key !== '');
    });

    const activePromptSetting = computed(() => {
        if (normalizedPromptSettings.value.length === 0) {
            return null;
        }

        const selectedFeature = String(selectedPromptFeatureKey.value || '');
        const selectedSetting = normalizedPromptSettings.value.find((setting) => setting.feature_key === selectedFeature);

        return selectedSetting || normalizedPromptSettings.value[0];
    });

    const isEditMode = computed(() => Number(editingAgentId.value) > 0);

    const normalizedProviderOptions = computed(() => {
        if (!Array.isArray(props.providerOptions)) {
            return [];
        }

        return props.providerOptions
            .map((providerOption) => ({
                value: String(providerOption?.value || ''),
                label: String(providerOption?.label || providerOption?.value || ''),
            }))
            .filter((providerOption) => providerOption.value !== '' && providerOption.label !== '');
    });

    const currentAgentOptions = computed(() => {
        const provider = String(agentForm.provider || 'openai');
        const options = props.agentOptionsByProvider?.[provider];

        return Array.isArray(options) ? options : [];
    });

    const selectedProviderGuide = computed(() => {
        const providerKey = String(agentForm.provider || '').trim().toLowerCase();
        return providerApiKeyGuides[providerKey] ?? null;
    });

    watch(
        () => agentForm.provider,
        (provider) => {
            const selectedProvider = String(provider || 'openai');
            const options = props.agentOptionsByProvider?.[selectedProvider];

            if (!Array.isArray(options) || options.length === 0) {
                agentForm.agent_model = '';
                return;
            }

            const isCurrentModelValid = options.some((option) => option?.value === agentForm.agent_model);
            if (!isCurrentModelValid) {
                agentForm.agent_model = String(options[0]?.value || '');
            }
        },
    );

    const fillPromptFormFromSetting = (setting) => {
        promptForm.clearErrors();
        promptForm.feature_key = String(setting?.feature_key || '');
        promptForm.system_prompt = String(setting?.system_prompt || '');
        promptForm.feature_prompt = String(setting?.feature_prompt || '');
        promptForm.feature_prompt_config = normalizeFeaturePromptConfig(
            promptForm.feature_key,
            setting?.feature_prompt_config || {},
        );
        promptForm.is_active = Boolean(setting?.is_active ?? true);
    };

    const resolveStructuredFeaturePromptPayload = () => {
        const featureKey = String(promptForm.feature_key || '').trim();
        const schema = getFeaturePromptSchema(featureKey);
        const hasStructuredFields = Array.isArray(schema?.fields) && schema.fields.length > 0;

        if (!hasStructuredFields) {
            return {
                featurePrompt: String(promptForm.feature_prompt || '').trim(),
                featurePromptConfig: null,
            };
        }

        const normalizedConfig = normalizeFeaturePromptConfig(
            featureKey,
            promptForm.feature_prompt_config || {},
        );

        return {
            featurePrompt: buildFeaturePromptFromConfig(featureKey, normalizedConfig),
            featurePromptConfig: normalizedConfig,
        };
    };

    const fillPromptTestFormFromSetting = (setting) => {
        const featureKey = String(setting?.feature_key || '');
        const testInputTemplate = String(setting?.test_input_template || '');

        promptTestForm.clearErrors();
        promptTestForm.feature_key = featureKey;
        promptTestForm.test_input = testInputTemplate;
        promptTestForm.test_input_config = resolveTestInputConfigFromTemplate(featureKey, testInputTemplate);
    };

    const resolveStructuredTestInputPayload = () => {
        const featureKey = String(promptTestForm.feature_key || '').trim();
        const schema = getTestInputSchema(featureKey);
        const hasStructuredBuilder = Boolean(schema?.title || schema?.description);

        if (!hasStructuredBuilder) {
            return {
                testInput: String(promptTestForm.test_input || '').trim(),
            };
        }

        const normalizedConfig = normalizeTestInputConfig(
            featureKey,
            promptTestForm.test_input_config || {},
        );

        return {
            testInput: buildTestInputFromConfig(featureKey, normalizedConfig),
        };
    };

    watch(
        normalizedPromptSettings,
        (settings) => {
            const firstSetting = settings[0] || null;
            if (!firstSetting) {
                selectedPromptFeatureKey.value = '';
                fillPromptFormFromSetting(null);
                return;
            }

            const hasSelected = settings.some(
                (setting) => setting.feature_key === String(selectedPromptFeatureKey.value || ''),
            );

            if (!hasSelected) {
                selectedPromptFeatureKey.value = firstSetting.feature_key;
            }
        },
        {
            immediate: true,
            deep: true,
        },
    );

    watch(
        promptTestResult,
        (result) => {
            const featureKey = String(result?.feature_key || '').trim();
            if (featureKey === '') {
                return;
            }

            const hasFeature = normalizedPromptSettings.value.some(
                (setting) => setting.feature_key === featureKey,
            );

            if (hasFeature) {
                selectedPromptFeatureKey.value = featureKey;
            }
        },
        {
            immediate: true,
        },
    );

    watch(
        activePromptSetting,
        (setting, previousSetting) => {
            fillPromptFormFromSetting(setting);

            const currentFeatureKey = String(setting?.feature_key || '');
            const previousFeatureKey = String(previousSetting?.feature_key || '');
            if (currentFeatureKey !== previousFeatureKey) {
                fillPromptTestFormFromSetting(setting);
            }
        },
        {
            immediate: true,
            deep: false,
        },
    );

    const requestTable = (override = {}) => {
        const nextFilters = {
            ...tableFilters.value,
            ...override,
        };

        tableFilters.value = nextFilters;

        fetchPlatformAiAgents(aiAgentPath, nextFilters, {
            onStart: () => {
                tableLoading.value = true;
            },
            onFinish: () => {
                tableLoading.value = false;
            },
        });
    };

    const handleSearch = (search) => {
        requestTable({
            search,
            cursor: null,
        });
    };

    const handleSort = ({ key, direction }) => {
        requestTable({
            sort_by: key,
            sort_dir: direction,
            cursor: null,
        });
    };

    const handlePerPage = (perPage) => {
        requestTable({
            per_page: perPage,
            cursor: null,
        });
    };

    const handlePage = (payload) => {
        if (payload && typeof payload === 'object' && payload.type === 'cursor') {
            requestTable({
                cursor: String(payload.cursor || ''),
            });
        }
    };

    const resolveDefaultModel = (provider = 'openai') => {
        const options = props.agentOptionsByProvider?.[provider];
        if (!Array.isArray(options) || options.length === 0) {
            return '';
        }

        return String(options[0]?.value || '');
    };

    const resetAgentForm = () => {
        editingAgentId.value = null;
        editingApiKeyValue.value = '';
        showApiKey.value = false;

        agentForm.clearErrors();
        agentForm.provider = 'openai';
        agentForm.agent_model = resolveDefaultModel('openai') || 'gpt-5';
        agentForm.api_key = '';
        agentForm.remove_api_key = false;
        agentForm.priority_order = nextPriorityOrder.value;
        agentForm.monthly_token_limit = null;
        agentForm.is_active = true;
        agentForm.is_default = false;
        agentForm.is_failover_enabled = true;
    };

    const openCreateAgentModal = () => {
        resetAgentForm();
        isAgentModalOpen.value = true;
    };

    const closeAgentModal = () => {
        isAgentModalOpen.value = false;
        resetAgentForm();
    };

    const startEditAgent = (agent) => {
        const agentId = Number(agent?.id) || 0;
        if (agentId <= 0) return;

        editingAgentId.value = agentId;
        editingApiKeyValue.value = String(agent?.api_key_value || '');
        showApiKey.value = false;

        agentForm.clearErrors();
        agentForm.provider = String(agent?.provider || 'openai');
        agentForm.agent_model = String(agent?.agent_model || resolveDefaultModel(agentForm.provider));
        agentForm.api_key = editingApiKeyValue.value;
        agentForm.remove_api_key = false;
        agentForm.priority_order = Number(agent?.priority_order) || 1;
        agentForm.monthly_token_limit = agent?.monthly_token_limit !== null && agent?.monthly_token_limit !== undefined
            ? Number(agent.monthly_token_limit)
            : null;
        agentForm.is_active = Boolean(agent?.is_active);
        agentForm.is_default = Boolean(agent?.is_default);
        agentForm.is_failover_enabled = Boolean(agent?.is_failover_enabled);

        isAgentModalOpen.value = true;
    };

    const submitAgentForm = () => {
        const payload = (data) => {
            const rawApiKey = String(data.api_key || '').trim();
            const currentApiKeyValue = String(editingApiKeyValue.value || '').trim();
            const keepExistingApiKey = isEditMode.value
                && currentApiKeyValue !== ''
                && rawApiKey === currentApiKeyValue;

            return {
                provider: String(data.provider || '').trim().toLowerCase(),
                agent_model: String(data.agent_model || '').trim(),
                api_key: keepExistingApiKey ? '' : rawApiKey,
                remove_api_key: false,
                priority_order: Math.max(1, Number(data.priority_order) || 1),
                monthly_token_limit: data.monthly_token_limit === null || data.monthly_token_limit === ''
                    ? null
                    : Math.max(0, Number(data.monthly_token_limit) || 0),
                is_active: Boolean(data.is_active),
                is_default: Boolean(data.is_default),
                is_failover_enabled: Boolean(data.is_failover_enabled),
            };
        };

        if (isEditMode.value) {
            agentForm
                .transform(payload)
                .patch(aiAgentUpdatePath(editingAgentId.value), {
                    preserveScroll: true,
                    onSuccess: closeAgentModal,
                });
            return;
        }

        agentForm
            .transform(payload)
            .post(aiAgentStorePath, {
                preserveScroll: true,
                onSuccess: closeAgentModal,
            });
    };

    const toggleAgentStatus = (agent) => {
        const agentId = Number(agent?.id) || 0;
        if (agentId <= 0 || statusForm.processing) return;

        const nextStatus = !Boolean(agent?.is_active);
        statusForm.clearErrors();

        statusForm
            .transform(() => ({
                is_active: nextStatus,
            }))
            .patch(aiAgentStatusPath(agentId), {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    togglingAgentId.value = agentId;
                },
                onFinish: () => {
                    togglingAgentId.value = null;
                },
            });
    };

    const setDefaultAgent = (agent) => {
        const agentId = Number(agent?.id) || 0;
        if (agentId <= 0 || defaultForm.processing || Boolean(agent?.is_default)) return;

        defaultForm.clearErrors();

        defaultForm.patch(aiAgentDefaultPath(agentId), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                defaultingAgentId.value = agentId;
            },
            onFinish: () => {
                defaultingAgentId.value = null;
            },
        });
    };

    const testAgent = (agent) => {
        const agentId = Number(agent?.id) || 0;
        if (agentId <= 0 || testForm.processing) return;

        testForm.clearErrors();

        testForm
            .transform(() => ({ api_key: null }))
            .post(aiAgentTestPath(agentId), {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    testingAgentId.value = agentId;
                },
                onFinish: () => {
                    testingAgentId.value = null;
                },
            });
    };

    const deleteAgent = (agent) => {
        const agentId = Number(agent?.id) || 0;
        if (agentId <= 0 || deleteForm.processing) return;

        const confirmed = window.confirm(`Hapus agent ${String(agent?.name || '-') }?`);
        if (!confirmed) {
            return;
        }

        deleteForm.clearErrors();
        deleteForm.delete(aiAgentDeletePath(agentId), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                deletingAgentId.value = agentId;
            },
            onFinish: () => {
                deletingAgentId.value = null;
            },
        });
    };

    const selectPromptFeature = (featureKey) => {
        const nextFeatureKey = String(featureKey || '').trim();
        if (nextFeatureKey === '') {
            return;
        }

        selectedPromptFeatureKey.value = nextFeatureKey;
    };

    const submitPromptForm = () => {
        const featureKey = String(promptForm.feature_key || '').trim();
        if (featureKey === '' || promptForm.processing) {
            return;
        }

        const { featurePrompt, featurePromptConfig } = resolveStructuredFeaturePromptPayload();

        promptForm
            .transform((data) => ({
                system_prompt: String(data.system_prompt || '').trim(),
                feature_prompt: featurePrompt,
                feature_prompt_config: featurePromptConfig,
                is_active: Boolean(data.is_active),
            }))
            .patch(aiPromptUpdatePath(featureKey), {
                preserveScroll: true,
                preserveState: true,
            });
    };

    const submitPromptTestForm = () => {
        const featureKey = String(promptTestForm.feature_key || '').trim();
        if (featureKey === '' || promptTestForm.processing) {
            return;
        }

        const { featurePrompt, featurePromptConfig } = resolveStructuredFeaturePromptPayload();
        const { testInput } = resolveStructuredTestInputPayload();

        promptTestForm
            .transform((data) => ({
                test_input: testInput,
                system_prompt: String(promptForm.system_prompt || '').trim(),
                feature_prompt: featurePrompt,
                feature_prompt_config: featurePromptConfig,
            }))
            .post(aiPromptTestPath(featureKey), {
                preserveScroll: true,
                preserveState: true,
            });
    };

    const logout = () => {
        logoutForm.post('/logout');
    };

    return {
        dashboardPath,
        flashStatus,
        flashStatusLevel,
        promptTestResult,
        agentError,
        menuItems,
        tableFilters,
        tableLoading,
        agentForm,
        promptForm,
        promptTestForm,
        statusForm,
        defaultForm,
        deleteForm,
        testForm,
        isAgentModalOpen,
        isEditMode,
        showApiKey,
        normalizedPromptSettings,
        selectedPromptFeatureKey,
        normalizedProviderOptions,
        currentAgentOptions,
        selectedProviderGuide,
        togglingAgentId,
        testingAgentId,
        defaultingAgentId,
        deletingAgentId,
        handleSearch,
        handleSort,
        handlePerPage,
        handlePage,
        openCreateAgentModal,
        closeAgentModal,
        startEditAgent,
        submitAgentForm,
        toggleAgentStatus,
        setDefaultAgent,
        testAgent,
        deleteAgent,
        selectPromptFeature,
        submitPromptForm,
        submitPromptTestForm,
        logout,
    };
};

