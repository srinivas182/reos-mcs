/** Phone numbers for sign-in: what people type, and how the code screen shows it. */

/** Turns what people type into +15125550100. US numbers may skip the +1 (Texas launch). */
export function normalisePhone(input: string): string | null {
  const digits = input.replace(/[^\d+]/g, '');

  if (/^\+[1-9]\d{7,14}$/.test(digits)) {
    return digits;
  }

  if (/^\d{10}$/.test(digits)) {
    return `+1${digits}`;
  }

  if (/^1\d{10}$/.test(digits)) {
    return `+${digits}`;
  }

  return null;
}

/** "(713) •••-0142" for US numbers, "+44 ••••0142" elsewhere. */
export function maskPhone(phone: string): string {
  const us = /^\+1(\d{3})\d{3}(\d{4})$/.exec(phone);

  if (us) {
    return `(${us[1]}) •••-${us[2]}`;
  }

  return `${phone.slice(0, 3)} ••••${phone.slice(-4)}`;
}

