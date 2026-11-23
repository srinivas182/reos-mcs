export type Delivery = 'classroom' | 'live_online' | 'self_paced' | 'ai_avatar';
export type ApprovalStatus = 'pending' | 'approved' | 'withdrawn';
export type ShownStatus = ApprovalStatus | 'expired';

export type SeatTime = {
    max_hours_per_day: number | null;
    checkin_minutes: number | null;
    presence_percent: number | null;
    makeup_min_percent: number | null;
    makeup_days: number | null;
};

/** A course approval on screen 50 (A7.1). */
export type ApprovalRow = SeatTime & {
    id: number;
    course: { id: number; title: string; type: string; delivery: Delivery };
    state_code: string;
    delivery: Delivery;
    number: string | null;
    hours: number | null;
    approved_on: string | null;
    expires_on: string | null;
    days_left: number | null;
    status: ApprovalStatus;
    shown_status: ShownStatus;
    labels_course: boolean;
    note: string | null;
};

export type ApprovalCourse = {
    id: number;
    title: string;
    type: string;
    delivery: Delivery;
    hours: number | null;
    states: string[];
    needs_approval: boolean;
};

export type ApprovalOptions = {
    courses: ApprovalCourse[];
    states: { code: string; name: string }[];
    deliveries: Delivery[];
    defaults: Record<string, SeatTime>;
};

/** The school dashboard's approvals card (screen 49). */
export type ApprovalSummary = {
    courses: number;
    in_force: number;
    attention: {
        id: number;
        course: string;
        state_code: string;
        number: string | null;
        expires_on: string | null;
        days_left: number | null;
        status: ShownStatus;
    }[];
};

/** Tone of the status chip. */
export function statusTone(
    status: ShownStatus,
    daysLeft: number | null,
): string {
    if (status === 'expired' || status === 'withdrawn') {
        return 'bg-reos-tone-danger-bg text-reos-tone-danger-fg';
    }

    if (status === 'pending') {
        return 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg';
    }

    return daysLeft !== null && daysLeft <= 60
        ? 'bg-reos-tone-warning-bg text-reos-tone-warning-fg'
        : 'bg-reos-tone-success-bg text-reos-tone-success-fg';
}

/** "Aug 31, 2028" from a date. */
export function approvalDate(day: string | null, language: string): string {
    return day
        ? new Intl.DateTimeFormat(language, {
              dateStyle: 'medium',
              timeZone: 'UTC',
          }).format(new Date(`${day}T00:00:00Z`))
        : '';
}
