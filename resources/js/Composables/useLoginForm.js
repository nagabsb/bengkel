import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { submitLogin } from '../Services/authService';

export const useLoginForm = () => {
    const page = usePage();
    const showPassword = ref(false);

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const flashStatus = computed(() => page.props.flash?.status ?? '');

    const submit = () => {
        submitLogin(form);
    };

    return {
        form,
        flashStatus,
        showPassword,
        submit,
    };
};
