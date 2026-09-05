export interface FrontendConfig {
  donationsEnabled: boolean;
  kofiUrl: string | null;
  impressumEnabled: boolean;
}

function parseDefaultOnFlag(value: string | undefined): boolean {
  return value?.trim().toLowerCase() !== 'false';
}

const kofiUrl = import.meta.env.VITE_KOFI_URL?.trim();

export const frontendConfig: FrontendConfig = {
  donationsEnabled: parseDefaultOnFlag(import.meta.env.VITE_DONATIONS_ENABLED),
  kofiUrl: kofiUrl || null,
  impressumEnabled: parseDefaultOnFlag(import.meta.env.VITE_IMPRESSUM_ENABLED),
};

export function getDonationUrl(config: FrontendConfig): string | null {
  return config.donationsEnabled ? config.kofiUrl : null;
}
