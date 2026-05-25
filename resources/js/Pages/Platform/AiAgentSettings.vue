<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import AiAgentTableCard from './AiAgent/Components/AiAgentTableCard.vue';
import AiAgentFormCard from './AiAgent/Components/AiAgentFormCard.vue';
import AiPromptSettingCard from './AiAgent/Components/AiPromptSettingCard.vue';
import { usePlatformAiAgentPage } from './Composables/usePlatformAiAgentPage';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    agents: {
        type: Object,
        default: () => ({
            mode: 'cursor',
            data: [],
            per_page: 10,
            total: 0,
            from: 0,
            to: 0,
            current_cursor: null,
            next_cursor: null,
            prev_cursor: null,
            has_more_pages: false,
        }),
    },
    agentFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'priority_order',
            sort_dir: 'asc',
            per_page: 10,
            cursor: null,
        }),
    },
    agentSummary: {
        type: Object,
        default: () => ({
            total_agents: 0,
            active_agents: 0,
            default_agent_id: null,
            next_priority_order: 1,
        }),
    },
    failoverOrder: {
        type: Array,
        default: () => [],
    },
    providerOptions: {
        type: Array,
        default: () => [],
    },
    agentOptionsByProvider: {
        type: Object,
        default: () => ({}),
    },
    promptSettings: {
        type: Array,
        default: () => [],
    },
});

const {
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
} = usePlatformAiAgentPage(props);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflowY = ref('');

const resolvePageScrollContainer = () => {
    if (!pageContentRef.value || typeof pageContentRef.value.closest !== 'function') {
        return null;
    }

    const container = pageContentRef.value.closest('.dashboard-scroll');
    return container instanceof HTMLElement ? container : null;
};

const setPageScrollLock = (isLocked) => {
    const container = lockedScrollContainer.value ?? resolvePageScrollContainer();
    if (!(container instanceof HTMLElement)) {
        return;
    }

    if (isLocked) {
        lockedScrollContainer.value = container;
        previousOverflowY.value = container.style.overflowY;
        container.style.overflowY = 'hidden';
        return;
    }

    container.style.overflowY = previousOverflowY.value;
    previousOverflowY.value = '';
    lockedScrollContainer.value = null;
};

watch(
    isAgentModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

onBeforeUnmount(() => {
    setPageScrollLock(false);
});
</script>

<template>
    <Head title="AI Agent" />

    <AppDashboardLayout
        title="AI Agent"
        subtitle="Kelola multi-agent, default, failover, dan kuota AI"
        role-label="Superadmin"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <AiAgentTableCard
                :agents="agents"
                :filters="tableFilters"
                :summary="agentSummary"
                :failover-order="failoverOrder"
                :flash-status="flashStatus"
                :flash-status-level="flashStatusLevel"
                :table-loading="tableLoading"
                :status-processing="statusForm.processing"
                :default-processing="defaultForm.processing"
                :test-processing="testForm.processing"
                :delete-processing="deleteForm.processing"
                :toggling-agent-id="togglingAgentId"
                :defaulting-agent-id="defaultingAgentId"
                :testing-agent-id="testingAgentId"
                :deleting-agent-id="deletingAgentId"
                :error-message="agentError"
                @create="openCreateAgentModal"
                @search="handleSearch"
                @sort="handleSort"
                @per-page="handlePerPage"
                @page="handlePage"
                @edit="startEditAgent"
                @toggle-status="toggleAgentStatus"
                @set-default="setDefaultAgent"
                @test="testAgent"
                @delete="deleteAgent"
            />

            <AiPromptSettingCard
                :prompt-settings="normalizedPromptSettings"
                :selected-feature-key="selectedPromptFeatureKey"
                :form="promptForm"
                :processing="promptForm.processing"
                :test-form="promptTestForm"
                :test-result="promptTestResult"
                @select-feature="selectPromptFeature"
                @submit="submitPromptForm"
                @test-output="submitPromptTestForm"
            />
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isAgentModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeAgentModal"
                />

                <div class="relative z-20 w-full max-w-3xl">
                    <AiAgentFormCard
                        :is-edit-mode="isEditMode"
                        :form="agentForm"
                        :provider-options="normalizedProviderOptions"
                        :agent-options="currentAgentOptions"
                        :selected-provider-guide="selectedProviderGuide"
                        :show-api-key="showApiKey"
                        @close="closeAgentModal"
                        @submit="submitAgentForm"
                        @toggle-api-key="showApiKey = !showApiKey"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>



