const { createApp } = Vue;

createApp({
    data() {
        return { draft: '', todos: [] };
    },
    computed: {
        remaining() {
            return this.todos.filter((todo) => !todo.done).length;
        },
    },
    methods: {
        add() {
            if (this.draft.trim() === '') {
                return;
            }
            this.todos.push({ text: this.draft.trim(), done: false });
            this.draft = '';
        },
        remove(index) {
            this.todos.splice(index, 1);
        },
    },
}).mount('#app');
