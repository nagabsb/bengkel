<script setup>
import { computed } from 'vue';

const props = defineProps({
    activities: {
        type: Object,
        default: () => ({}),
    },
    revealStyle: {
        type: Object,
        default: () => ({}),
    },
});

const activityMeta = computed(() => ({
    title: props.activities?.title || 'Aktivitas Terbaru',
    subtitle: props.activities?.subtitle || 'Update real-time sistem',
    items: props.activities?.items || [],
}));
</script>

<template>
    <article
        class="dashboard-reveal rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm transition-colors motion-safe:transition-transform motion-safe:duration-200 motion-safe:ease-out motion-safe:hover:-translate-y-0.5 dark:border-emerald-400/30 dark:bg-slate-800"
        :style="revealStyle"
    >
        <h2 class="text-2xl font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ activityMeta.title }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ activityMeta.subtitle }}</p>

        <div class="mt-4 space-y-3">
            <article
                v-for="(activity, activityIndex) in activityMeta.items"
                :key="`${activity.title}-${activityIndex}`"
                class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-600 dark:bg-slate-700/80"
            >
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 h-2.5 w-2.5 shrink-0 rounded-full" :class="activity.dotClass || 'bg-emerald-500'" />
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm font-semibold leading-snug text-slate-700 dark:text-slate-200 sm:truncate">{{ activity.title }}</p>
                        <p class="text-xs leading-relaxed text-slate-500 break-words dark:text-slate-400">{{ activity.description }}</p>
                        <p class="text-xs font-medium text-slate-400 dark:text-slate-500">{{ activity.time }}</p>
                    </div>
                </div>
            </article>

            <p v-if="activityMeta.items.length === 0" class="text-sm text-slate-500 dark:text-slate-400">Belum ada aktivitas.</p>
        </div>
    </article>
</template>
