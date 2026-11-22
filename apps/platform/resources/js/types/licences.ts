/** A state licence (A5.1; LicenceResource). */
export type LicenceStatus =
    | 'active'
    | 'inactive'
    | 'expired'
    | 'pending_review'
    | 'rejected';

export type BrokerRole =
    | 'sponsoring_broker'
    | 'designated_broker'
    | 'delegated_supervisor';

export type Licence = {
    id: number;
    state: { code: string; name: string; live: boolean };
    type: 'sales_agent' | 'broker';
    number: string;
    status: LicenceStatus;
    broker_role: BrokerRole | null;
    holder_name: string | null;
    licensed_on: string | null;
    expires_on: string | null;
    sponsor_name: string | null;
    first_renewal: boolean;
    verified_by: string | null;
    verified_at: string | null;
    review_note: string | null;
    tracked_only: boolean;
};

/** A state a licence can be added in. */
export type LicenceState = { code: string; name: string; live: boolean };
