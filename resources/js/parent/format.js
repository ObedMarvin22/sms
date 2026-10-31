/** "2026-10-28" → "Wed 28 Oct 2026" */
export function longDate(iso) {
    return new Date(`${iso.slice(0, 10)}T12:00:00`).toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/** "2026-10" → "October 2026" */
export function monthName(month) {
    return new Date(`${month}-01T12:00:00`).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
}

/** Moves a "YYYY-MM" month by some months. */
export function addMonths(month, by) {
    const [year, m] = month.split('-').map(Number);
    const date = new Date(year, m - 1 + by, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

/** "2026-10-28T09:00:00Z" → "28 Oct, 09:00" */
export function when(iso) {
    return new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

export const statusStyles = {
    present: 'bg-green-100 text-green-800',
    late: 'bg-amber-100 text-amber-800',
    absent: 'bg-red-100 text-red-800',
    excused: 'bg-sky-100 text-sky-800',
};

export const statusLabels = {
    present: 'Present',
    late: 'Late',
    absent: 'Absent',
    excused: 'Excused',
};
