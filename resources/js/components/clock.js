/**
 * Live header clock: "Tue, Sep 29, 2026 · 10:42 AM", refreshed every second.
 */
export default function clock() {
    return {
        date: '',
        time: '',
        timer: null,

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },

        destroy() {
            clearInterval(this.timer);
        },

        tick() {
            const now = new Date();

            this.date = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            this.time = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        },
    };
}
