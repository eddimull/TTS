/** Formats a date-only 'YYYY-MM-DD' without shifting it across timezones. */
export function formatDueDate(date, { year = true } = {}) {
  if (!date) return '';
  return new Date(`${date}T12:00:00`).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    ...(year ? { year: 'numeric' } : {}),
  });
}

export function formatMoney(amount) {
  return `$${Number(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

/** The deposit percent as a label: 25.00 -> "Deposit (25%)", flat -> "Deposit". */
export function depositLabel(depositType, depositValue) {
  if (depositType === 'amount') return 'Deposit';
  return `Deposit (${parseFloat(depositValue)}%)`;
}
