import { onBeforeUnmount, ref, watch } from 'vue';

export const useDebounce = (source, delay = 350) => {
    const debouncedValue = ref(source?.value);
    let timer = null;

    watch(
        source,
        (nextValue) => {
            if (timer) {
                clearTimeout(timer);
            }

            timer = setTimeout(() => {
                debouncedValue.value = nextValue;
            }, delay);
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        if (timer) {
            clearTimeout(timer);
        }
    });

    return debouncedValue;
};
