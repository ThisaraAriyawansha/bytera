/**
 * Live header clock: "Tue, Sep 29, 2026 · 10:42 AM", refreshed every second.
 * Rendered in the app's timezone so it matches server-side times regardless of the device clock.
 */
export default function clock(timeZone = 'Asia/Colombo') {
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

            this.date = now.toLocaleDateString('en-US', { timeZone, weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            this.time = now.toLocaleTimeString('en-US', { timeZone, hour: 'numeric', minute: '2-digit' });
        },
    };
}
