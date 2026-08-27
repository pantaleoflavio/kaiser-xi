function configured(value: string | undefined): string | null {
  const normalized = value?.trim();
  return normalized || null;
}

/** Mandatory deployment facts. Production must not launch while required values are null. */
export const legalConfig = {
  controllerName: configured(import.meta.env.VITE_LEGAL_CONTROLLER_NAME),
  postalAddress: configured(import.meta.env.VITE_LEGAL_POSTAL_ADDRESS),
  contactEmail: configured(import.meta.env.VITE_LEGAL_CONTACT_EMAIL),
  hostingProvider: configured(import.meta.env.VITE_LEGAL_HOSTING_PROVIDER),
  databaseProvider: configured(import.meta.env.VITE_LEGAL_DATABASE_PROVIDER),
  emailProvider: configured(import.meta.env.VITE_LEGAL_EMAIL_PROVIDER),
  supervisoryAuthority: configured(import.meta.env.VITE_LEGAL_SUPERVISORY_AUTHORITY),
  optionalCommercialDetails: configured(import.meta.env.VITE_LEGAL_COMMERCIAL_DETAILS),
} as const;
