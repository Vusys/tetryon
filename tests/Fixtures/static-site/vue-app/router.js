const { createApp } = Vue;

// A tiny hash router (no extra dependency): the active route is derived from
// window.location.hash, and content swaps on hashchange — no page reload.
createApp({
    data() {
        return { route: location.hash.slice(1) || '/' };
    },
    computed: {
        userId() {
            const match = this.route.match(/^\/users\/(\d+)$/);
            return match ? match[1] : null;
        },
    },
    mounted() {
        window.addEventListener('hashchange', () => {
            this.route = location.hash.slice(1) || '/';
        });
    },
}).mount('#app');
