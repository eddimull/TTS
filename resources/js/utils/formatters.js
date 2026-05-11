/**
 * Utility functions for formatting dates, currency, and other values
 */

/**
 * Format a date string or Date object to localized date string (MM/DD/YYYY)
 * @param {string|Date} value - Date to format
 * @returns {string} Formatted date string
 */
export function formatDate(value) {
    if (!value) return '';
    const date = new Date(value);
    return date.toLocaleDateString('en-US', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

/**
 * Format a number as USD currency
 * @param {number|string} value - Amount to format
 * @param {number} minimumFractionDigits - Minimum decimal places (default: 2)
 * @returns {string} Formatted currency string
 */
export function formatCurrency(value, minimumFractionDigits = 2) {
    if (value === null || value === undefined) return '$0.00';
    const numValue = typeof value === 'string' ? parseFloat(value) : value;
    return numValue.toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits,
    });
}

/**
 * Format a number as USD currency without the $ symbol
 * @param {number|string} value - Amount to format
 * @returns {string} Formatted number with 2 decimal places
 */
export function formatMoney(value) {
    if (value === null || value === undefined) return '0.00';
    const numValue = typeof value === 'string' ? parseFloat(value) : value;
    return numValue.toFixed(2);
}

/**
 * Format a price value (alias for formatMoney)
 * @param {number|string} price - Price to format
 * @returns {string} Formatted price with 2 decimal places
 */
export function formatPrice(price) {
    return formatMoney(price);
}

/**
 * Format a time string to localized time (HH:MM AM/PM)
 * @param {string} timeString - Time string in HH:MM:SS format
 * @returns {string} Formatted time string
 */
export function formatTime(timeString) {
    if (!timeString) return '';
    return new Date(`2000-01-01T${timeString}`).toLocaleTimeString([], {
        timeStyle: 'short',
    });
}

/**
 * Format a date range between two dates
 * @param {string|Date} start - Start date
 * @param {string|Date} end - End date
 * @returns {string} Formatted date range
 */
export function formatDateRange(start, end) {
    if (!start) return '';
    const startDate = new Date(start);
    if (!end) return formatDate(start);
    const endDate = new Date(end);
    const sameDay = startDate.toDateString() === endDate.toDateString();
    if (sameDay) return formatDate(start);
    const sameMonth =
        startDate.getMonth() === endDate.getMonth() &&
        startDate.getFullYear() === endDate.getFullYear();
    if (sameMonth) {
        const monthDay = { month: 'short', day: 'numeric' };
        const dayOnly = { day: 'numeric' };
        return `${startDate.toLocaleDateString(undefined, monthDay)}–${endDate.toLocaleDateString(undefined, dayOnly)}`;
    }
    return `${formatDate(start)} – ${formatDate(end)}`;
}
