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
