const DAY_MS = 86400000;
const NEARBY_DAYS = 14;

const toDay = (value) => {
  if (!value) return null;
  const d = new Date(String(value).slice(0, 10) + 'T00:00:00');
  return Number.isNaN(d.getTime()) ? null : d.getTime();
};

/**
 * Groups picker options ({id, name|title, date}) around a stay.
 * With a check-in: "During your stay" / "Nearby" (±14 days) / "Everything
 * else". Without: one unlabeled group, date-ascending, undated last.
 */
export function groupLinkOptions(options, checkIn, checkOut) {
  const inDay = toDay(checkIn);
  const outDay = toDay(checkOut) ?? inDay;

  if (inDay == null) {
    const sorted = [...options].sort((a, b) => {
      const da = toDay(a.date); const db = toDay(b.date);
      if (da == null && db == null) return 0;
      if (da == null) return 1;
      if (db == null) return -1;
      return da - db;
    });
    return [{ label: '', options: sorted }];
  }

  const during = []; const nearby = []; const rest = [];
  for (const o of options) {
    const day = toDay(o.date);
    if (day != null && day >= inDay && day <= outDay) during.push(o);
    else if (day != null && Math.abs(day - inDay) <= NEARBY_DAYS * DAY_MS) nearby.push(o);
    else rest.push(o);
  }
  const dist = (o) => Math.abs(toDay(o.date) - inDay);
  during.sort((a, b) => dist(a) - dist(b));
  nearby.sort((a, b) => dist(a) - dist(b));
  rest.sort((a, b) => {
    const da = toDay(a.date); const db = toDay(b.date);
    if (da == null && db == null) return 0;
    if (da == null) return 1;
    if (db == null) return -1;
    return da - db;
  });

  return [
    { label: 'During your stay', options: during },
    { label: 'Nearby', options: nearby },
    { label: 'Everything else', options: rest },
  ].filter(g => g.options.length);
}
