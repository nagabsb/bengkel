export const submitLogin = (form) => {
    form.post('/login', {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
    });
};
