/**
 * Links a spoken name to a contact on this phone (B4.3b, ADR 0018). The
 * search runs on the phone, so no address book leaves it; only the chosen
 * contact's id and name are saved with the to-do. Permission is asked only
 * when a to-do names someone. CRM contacts join in Sprint 5.
 */
import { Contact, ContactField, getPermissionsAsync, requestPermissionsAsync } from 'expo-contacts';

export type PhoneContact = { id: string; name: string };

export type ContactMatches = { allowed: false } | { allowed: true; byName: Record<string, PhoneContact[]> };

const LIMIT = 5;

export async function matchContacts(names: string[]): Promise<ContactMatches> {
  if (names.length === 0) {
    return { allowed: true, byName: {} };
  }

  try {
    let permission = await getPermissionsAsync();

    if (!permission.granted && permission.canAskAgain) {
      permission = await requestPermissionsAsync();
    }

    if (!permission.granted) {
      return { allowed: false };
    }

    const byName: Record<string, PhoneContact[]> = {};

    for (const name of names) {
      const found = await Contact.getAllDetails([ContactField.FULL_NAME] as const, { name, limit: LIMIT });
      byName[name] = found.map((contact) => ({ id: contact.id, name: contact.fullName?.trim() || name }));
    }

    return { allowed: true, byName };
  } catch {
    return { allowed: false };
  }
}

export type PhoneRecord = {
  id: string;
  first_name: string | null;
  last_name: string | null;
  emails: string[];
  phones: string[];
  birthday: string | null;
};

export type PhoneContacts = { allowed: false } | { allowed: true; contacts: PhoneRecord[] };

const pad = (n: number) => String(n).padStart(2, '0');

/**
 * Every contact on the phone, for "Add phone contacts" (B7.1, Connections
 * and Sync journey): names, emails, phone numbers and birthdays only.
 * Asked only when the person taps Add; REOS never writes to the phone.
 */
export async function readPhoneContacts(): Promise<PhoneContacts> {
  try {
    let permission = await getPermissionsAsync();

    if (!permission.granted && permission.canAskAgain) {
      permission = await requestPermissionsAsync();
    }

    if (!permission.granted) {
      return { allowed: false };
    }

    const found = await Contact.getAllDetails(
      [ContactField.GIVEN_NAME, ContactField.FAMILY_NAME, ContactField.EMAILS, ContactField.PHONES, ContactField.BIRTHDAY] as const,
    );

    return {
      allowed: true,
      contacts: found.map((contact) => {
        const birthday = contact.birthday;

        return {
          id: contact.id,
          first_name: contact.givenName?.trim() || null,
          last_name: contact.familyName?.trim() || null,
          emails: (contact.emails ?? []).map((e) => e.address ?? '').filter((e) => e !== '').slice(0, 10),
          phones: (contact.phones ?? []).map((p) => p.number ?? '').filter((p) => p !== '').slice(0, 10),
          birthday: birthday?.year ? `${birthday.year}-${pad(birthday.month)}-${pad(birthday.day)}` : null,
        };
      }),
    };
  } catch {
    return { allowed: false };
  }
}
