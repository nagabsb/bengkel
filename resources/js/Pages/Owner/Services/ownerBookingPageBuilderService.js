import { router } from '@inertiajs/vue3';

export const fetchOwnerBookingPageBuilder = (path, options = {}) => {
    router.get(path, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const updateOwnerBookingPageBuilder = (form, path, options = {}) => {
    const { onFinish, ...otherOptions } = options;

    form
        .transform((data) => ({
            ...data,
            _method: 'patch',
        }))
        .post(path, {
            preserveScroll: true,
            forceFormData: true,
            ...otherOptions,
            onFinish: () => {
                form.transform((data) => data);

                if (typeof onFinish === 'function') {
                    onFinish();
                }
            },
        });
};
