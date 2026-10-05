import { formatMoney } from '../money';

const STORAGE_KEY = 'finance.openingBalance';

/**
 * Finance → Daily Balance (cash book, SPEC §8.19): an editable opening balance kept in this browser's localStorage
 * (as the original app did), then each day's income, expenses, net and the running closing balance.
 */
export default function dailyBalance(config) {
    return {
        money: formatMoney,
        days: config.days,
        opening: readOpening(),

        get rows() {
            let running = Math.round(Number(this.opening || 0) * 100);

            return this.days.map((day) => {
                running += Math.round(day.net * 100);

                return { ...day, closing: running / 100 };
            });
        },

        get closingBalance() {
            const rows = this.rows;

            return rows.length > 0 ? rows[rows.length - 1].closing : Number(this.opening || 0);
        },

        get exportUrl() {
            const url = new URL(config.exportUrl, window.location.origin);
            url.searchParams.set('opening', String(Number(this.opening || 0)));

            return url.toString();
        },

        saveOpening() {
            try {
                window.localStorage.setItem(STORAGE_KEY, String(Number(this.opening || 0)));
            } catch (error) {
                // Storage can be blocked (private mode); the balance still works for this page view.
            }
        },
    };
}

function readOpening() {
    try {
        return window.localStorage.getItem(STORAGE_KEY) ?? '0';
    } catch (error) {
        return '0';
    }
}
