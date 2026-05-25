<script setup>
defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button
        class="mt-1 h-12 w-full cursor-pointer rounded-2xl bg-emerald-400 text-base font-bold text-slate-950 shadow-lg shadow-emerald-500/30 transition hover:brightness-105 disabled:cursor-wait disabled:opacity-75"
        :disabled="disabled"
        type="submit"
    >
        <slot />
    </button>
</template>
