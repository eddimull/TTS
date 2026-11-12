/**
 * Money owed on a booking, from the backend's `amount_due`. That figure nets
 * out any balance the band wrote off, which `price - amount_paid` would miss;
 * `amount_paid` stays real money received. Overpaid bookings come back
 * negative, as before.
 */
export const amountDue = (booking) => parseFloat(booking?.amount_due) || 0;

/** Owed amount for "unpaid" totals: never below zero. */
export const unpaidAmount = (booking) => Math.max(0, amountDue(booking));

/** Sum of amount_due across bookings (overpayments offset, as before). */
export const sumAmountDue = (bookings) =>
    (bookings ?? []).reduce((total, booking) => total + amountDue(booking), 0);
