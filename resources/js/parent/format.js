const date = (iso) => new Date(`${iso.slice(0, 10)}T12:00:00`);

/** "2026-10-28" → "Wed 28 Oct 2026" */
export function longDate(iso) {
    return date(iso).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
}

/** "2026-10-28" → "28 Oct" */
export function shortDate(iso) {
    return date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

/** "2026-10" → "October 2026" */
export function monthName(month) {
    return new Date(`${month}-01T12:00:00`).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
}

/** Moves a "YYYY-MM" month by some months. */
export function addMonths(month, by) {
    const [year, m] = month.split('-').map(Number);
    const moved = new Date(year, m - 1 + by, 1);

    return `${moved.getFullYear()}-${String(moved.getMonth() + 1).padStart(2, '0')}`;
}

/** "YYYY-MM" for today, in the phone's own time zone. */
export function thisMonth() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

/** A notice's time: "09:40" today, "Yesterday", or "28 Oct". */
export function when(iso) {
    const at = new Date(iso);
    const today = new Date();
    const days = Math.floor((today.setHours(0, 0, 0, 0) - new Date(at).setHours(0, 0, 0, 0)) / 86_400_000);

    if (days === 0) {
        return at.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }

    return days === 1 ? 'Yesterday' : at.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

/** "+233244123456" → "024 412 3456", the way parents write their number. */
export function phone(international) {
    const national = /^\+233(\d{9})$/.exec(international ?? '')?.[1];

    return national ? `0${national.slice(0, 2)} ${national.slice(2, 5)} ${national.slice(5)}` : (international ?? '');
}

/** "Good morning" / "Good afternoon" / "Good evening". */
export function greeting() {
    const hour = new Date().getHours();

    return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
}

/**
 * The class register's marks, as teachers write them.
 */
export const marks = {
    present: { letter: 'P', label: 'Present', chip: 'bg-present-soft text-present', dot: 'bg-present', text: 'text-present' },
    late: { letter: 'L', label: 'Late', chip: 'bg-late-soft text-late', dot: 'bg-late', text: 'text-late' },
    absent: { letter: 'A', label: 'Absent', chip: 'bg-absent-soft text-absent', dot: 'bg-absent', text: 'text-absent' },
    excused: { letter: 'E', label: 'Excused', chip: 'bg-excused-soft text-excused', dot: 'bg-excused', text: 'text-excused' },
};
