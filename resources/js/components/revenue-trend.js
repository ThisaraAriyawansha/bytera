import { formatMoney } from '../money';

const TOP_PADDING = 8;
const MAX_TICKS = 7;

/**
 * Compact axis amount: "Rs. 950", "Rs. 12.5k", "Rs. 1.2M".
 */
function compactMoney(amount) {
    if (amount >= 1_000_000) {
        return `Rs. ${+(amount / 1_000_000).toFixed(1)}M`;
    }

    if (amount >= 1_000) {
        return `Rs. ${+(amount / 1_000).toFixed(1)}k`;
    }

    return `Rs. ${Math.round(amount)}`;
}

/**
 * Dashboard → revenue hero trend chart (SPEC §8.3): the current period as a red area, the previous period as a
 * dashed grey line, and a hover crosshair with a red dot and a dark tooltip (date, amount, "prev" amount).
 * The SVG is drawn in a 0–100 box stretched to the card, so lines use non-scaling strokes and the dot,
 * crosshair and tooltip are HTML positioned in percent.
 */
export default function revenueTrend({ points }) {
    const values = points.flatMap((point) => [point.current, point.previous]);
    const max = Math.max(...values, 0) || 1;

    return {
        money: formatMoney,
        points,
        active: null,

        x(index) {
            return this.points.length <= 1 ? 50 : (index / (this.points.length - 1)) * 100;
        },

        y(amount) {
            return 100 - (amount / max) * (100 - TOP_PADDING);
        },

        line(key) {
            return this.points
                .map((point, index) => `${index === 0 ? 'M' : 'L'} ${this.x(index).toFixed(3)} ${this.y(point[key]).toFixed(3)}`)
                .join(' ');
        },

        get currentLine() {
            return this.line('current');
        },

        get previousLine() {
            return this.line('previous');
        },

        get area() {
            return `${this.currentLine} L ${this.x(this.points.length - 1)} 100 L ${this.x(0)} 100 Z`;
        },

        get gridLines() {
            return [1, 0.5].map((fraction) => ({ top: this.y(max * fraction), label: compactMoney(max * fraction) }));
        },

        get ticks() {
            const step = Math.max(1, Math.ceil(this.points.length / MAX_TICKS));

            return this.points
                .map((point, index) => ({ index, label: point.tick, left: this.x(index) }))
                .filter(({ index }) => index % step === 0);
        },

        get activePoint() {
            return this.active === null ? null : this.points[this.active];
        },

        get tooltipStyle() {
            const left = this.x(this.active ?? 0);

            return left > 60
                ? `right: ${100 - left}%; margin-right: 12px;`
                : `left: ${left}%; margin-left: 12px;`;
        },

        hover(event) {
            const rect = this.$refs.plot.getBoundingClientRect();
            const ratio = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));

            this.active = Math.round(ratio * (this.points.length - 1));
        },

        leave() {
            this.active = null;
        },
    };
}
