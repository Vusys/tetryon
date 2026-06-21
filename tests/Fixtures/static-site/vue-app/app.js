const { createApp } = Vue;

createApp({
    data() {
        return {
            view: 'home',
            count: 0,
            users: [],
            email: '',
            welcome: '',
        };
    },
    computed: {
        emailError() {
            if (this.email === '') {
                return '';
            }
            return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(this.email) ? '' : 'Invalid email';
        },
    },
    methods: {
        // Renders the list ~400ms later, so auto-wait has to do its job.
        loadUsers() {
            setTimeout(() => {
                this.users = ['Ada Lovelace', 'Alan Turing', 'Grace Hopper'];
            }, 400);
        },
        signUp() {
            if (this.email === '' || this.emailError !== '') {
                return;
            }
            this.welcome = 'Welcome, ' + this.email;
        },
    },
}).mount('#app');
