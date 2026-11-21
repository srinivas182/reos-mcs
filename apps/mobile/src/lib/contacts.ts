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
