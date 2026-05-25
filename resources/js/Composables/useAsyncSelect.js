import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useDebounce } from './useDebounce';

export const useAsyncSelect = (props, emit) => {
    const rootRef = ref(null);
    const triggerButtonRef = ref(null);
    const menuRef = ref(null);
    const searchInputRef = ref(null);
    const isOpen = ref(false);
    const query = ref('');
    const fixedMenuStyles = ref({});
    const highlightedIndex = ref(-1);
    const optionRefs = ref([]);

    const normalizedOptions = computed(() => props.options
        .map((option) => {
            const raw = option && typeof option === 'object' ? option : {};
            const value = Object.prototype.hasOwnProperty.call(raw, 'value') ? raw.value : null;
            const label = String(raw?.label ?? value ?? '');

            return {
                value,
                label,
                raw: {
                    ...raw,
                    value,
                    label,
                },
            };
        })
        .filter((option) => option.label !== ''));

    const isSameValue = (left, right) => {
        if (left === null || left === undefined || right === null || right === undefined) {
            return left === right;
        }

        return String(left) === String(right);
    };

    const selectedOption = computed(() => normalizedOptions.value.find((option) => isSameValue(option.value, props.modelValue)) || null);
    const debouncedQuery = useDebounce(query, 350);
    const filteredOptions = computed(() => {
        const keyword = String(debouncedQuery.value || '').trim().toLowerCase();
        if (keyword === '') {
            return normalizedOptions.value;
        }

        return normalizedOptions.value.filter((option) => option.label.toLowerCase().includes(keyword));
    });

    const highlightedOption = computed(() => filteredOptions.value[highlightedIndex.value] || null);

    const setHighlightedFromValue = () => {
        const selectedIndex = filteredOptions.value.findIndex((option) => isSameValue(option.value, props.modelValue));
        highlightedIndex.value = selectedIndex >= 0 ? selectedIndex : (filteredOptions.value.length > 0 ? 0 : -1);
    };

    const setOptionRef = (element, index) => {
        optionRefs.value[index] = element instanceof HTMLElement ? element : null;
    };

    const scrollHighlightedIntoView = async () => {
        if (!isOpen.value || highlightedIndex.value < 0) {
            return;
        }

        await nextTick();

        const highlightedElement = optionRefs.value[highlightedIndex.value];
        if (!(highlightedElement instanceof HTMLElement)) {
            return;
        }

        highlightedElement.scrollIntoView({
            block: 'nearest',
        });
    };

    const moveHighlight = (step) => {
        if (!isOpen.value || filteredOptions.value.length === 0) {
            return;
        }

        const lastIndex = filteredOptions.value.length - 1;
        const currentIndex = highlightedIndex.value;
        if (currentIndex < 0) {
            highlightedIndex.value = step > 0 ? 0 : lastIndex;
            return;
        }

        let nextIndex = currentIndex + step;
        if (nextIndex > lastIndex) {
            nextIndex = 0;
        }
        if (nextIndex < 0) {
            nextIndex = lastIndex;
        }

        highlightedIndex.value = nextIndex;
    };

    const closeMenu = () => {
        isOpen.value = false;
        query.value = '';
        fixedMenuStyles.value = {};
        highlightedIndex.value = -1;
        optionRefs.value = [];
    };

    const updateFixedMenuPosition = () => {
        if (!props.fixedMenu || !isOpen.value || typeof window === 'undefined' || !triggerButtonRef.value) {
            return;
        }

        const triggerRect = triggerButtonRef.value.getBoundingClientRect();
        const viewportPadding = 8;
        const minWidth = Math.max(triggerRect.width, 180);
        const requestedWidth = triggerRect.width * Math.max(1, Number(props.menuWidthMultiplier || 1));
        const width = Math.min(Math.max(requestedWidth, minWidth), window.innerWidth - (viewportPadding * 2));

        let left = triggerRect.left;
        if (left + width > window.innerWidth - viewportPadding) {
            left = window.innerWidth - width - viewportPadding;
        }
        if (left < viewportPadding) {
            left = viewportPadding;
        }

        const offset = Math.max(0, Number(props.menuOffset || 0));
        const menuHeight = menuRef.value?.offsetHeight || 360;
        let top = triggerRect.bottom + offset;
        const maxBottom = window.innerHeight - viewportPadding;

        if (top + menuHeight > maxBottom) {
            const aboveTop = triggerRect.top - menuHeight - offset;
            top = aboveTop >= viewportPadding ? aboveTop : Math.max(viewportPadding, maxBottom - menuHeight);
        }

        fixedMenuStyles.value = {
            position: 'fixed',
            top: `${Math.round(top)}px`,
            left: `${Math.round(left)}px`,
            width: `${Math.round(width)}px`,
        };
    };

    const handleViewportChange = () => {
        updateFixedMenuPosition();
    };

    const attachFixedMenuListeners = () => {
        if (typeof window === 'undefined' || typeof document === 'undefined') {
            return;
        }

        window.addEventListener('resize', handleViewportChange);
        document.addEventListener('scroll', handleViewportChange, true);
    };

    const detachFixedMenuListeners = () => {
        if (typeof window === 'undefined' || typeof document === 'undefined') {
            return;
        }

        window.removeEventListener('resize', handleViewportChange);
        document.removeEventListener('scroll', handleViewportChange, true);
    };

    const openMenu = async () => {
        if (props.disabled || isOpen.value) {
            return;
        }

        isOpen.value = true;
        await nextTick();
        updateFixedMenuPosition();
        setHighlightedFromValue();
        await scrollHighlightedIntoView();

        if (searchInputRef.value && typeof searchInputRef.value.focus === 'function') {
            try {
                searchInputRef.value.focus({ preventScroll: true });
            } catch {
                searchInputRef.value.focus();
            }
        }
    };

    const toggleMenu = () => {
        if (props.disabled) {
            return;
        }

        if (isOpen.value) {
            closeMenu();
            return;
        }

        openMenu();
    };

    const chooseOption = (option) => {
        emit('update:modelValue', option.value);
        closeMenu();
    };

    const chooseHighlightedOption = () => {
        if (!highlightedOption.value) {
            return;
        }

        chooseOption(highlightedOption.value);
    };

    const clearValue = () => {
        emit('update:modelValue', null);
        closeMenu();
    };

    const handleTriggerKeydown = (event) => {
        if (props.disabled) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (!isOpen.value) {
                openMenu();
                return;
            }

            moveHighlight(1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (!isOpen.value) {
                openMenu();
                return;
            }

            moveHighlight(-1);
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            if (!isOpen.value) {
                openMenu();
                return;
            }

            chooseHighlightedOption();
            return;
        }

        if (event.key === 'Escape') {
            closeMenu();
        }
    };

    const handleSearchKeydown = (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            moveHighlight(1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            moveHighlight(-1);
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            chooseHighlightedOption();
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeMenu();
        }
    };

    const handleDocumentClick = (event) => {
        if (!rootRef.value || !(event.target instanceof Node)) {
            return;
        }

        if (!rootRef.value.contains(event.target)) {
            closeMenu();
        }
    };

    const handleDocumentKeydown = (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    };

    watch(
        () => props.disabled,
        (disabled) => {
            if (disabled) {
                closeMenu();
            }
        },
    );

    watch(isOpen, async (open) => {
        if (props.fixedMenu) {
            if (open) {
                attachFixedMenuListeners();
                await nextTick();
                updateFixedMenuPosition();
            } else {
                detachFixedMenuListeners();
            }
        }

        if (open) {
            setHighlightedFromValue();
        }
    });

    watch(filteredOptions, () => {
        if (!isOpen.value) {
            return;
        }

        optionRefs.value = [];

        if (filteredOptions.value.length === 0) {
            highlightedIndex.value = -1;
            return;
        }

        if (String(debouncedQuery.value || '').trim() === '') {
            setHighlightedFromValue();
            return;
        }

        if (highlightedIndex.value < 0 || highlightedIndex.value >= filteredOptions.value.length) {
            highlightedIndex.value = 0;
        }
    });

    watch(highlightedIndex, () => {
        if (!isOpen.value) {
            return;
        }

        scrollHighlightedIntoView();
    });

    onMounted(() => {
        document.addEventListener('click', handleDocumentClick);
        document.addEventListener('keydown', handleDocumentKeydown);
    });

    onBeforeUnmount(() => {
        detachFixedMenuListeners();
        document.removeEventListener('click', handleDocumentClick);
        document.removeEventListener('keydown', handleDocumentKeydown);
    });

    return {
        rootRef,
        triggerButtonRef,
        menuRef,
        searchInputRef,
        isOpen,
        query,
        fixedMenuStyles,
        normalizedOptions,
        selectedOption,
        filteredOptions,
        highlightedIndex,
        setOptionRef,
        isSameValue,
        openMenu,
        closeMenu,
        toggleMenu,
        chooseOption,
        clearValue,
        handleTriggerKeydown,
        handleSearchKeydown,
    };
};
