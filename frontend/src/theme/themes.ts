export const themeIds = [
  'bavarian-red',
  'westphalian-yellow',
  'koenigsblau',
  'rhine-red-black',
  'blancos-white',
  'blaugrana-night',
  'colchonero',
  'gunners-red',
  'manchester-sky',
  'mersey-red',
  'vecchia-signora',
  'nerazzurro-night',
  'milano-red-black',
  'la-lupa-red-gold',
] as const;

export type ThemeId = (typeof themeIds)[number];

export type Theme = {
  id: ThemeId;
  name: string;
  swatches: readonly [string, string, string];
};

export const defaultThemeId: ThemeId = 'bavarian-red';

export const themes: readonly Theme[] = [
  { id: 'bavarian-red', name: 'Bavarian Red', swatches: ['#dc052d', '#ffffff', '#111c2e'] },
  {
    id: 'westphalian-yellow',
    name: 'Westphalian Yellow',
    swatches: ['#facc15', '#09090b', '#ffffff'],
  },
  { id: 'koenigsblau', name: 'Königsblau', swatches: ['#004d9d', '#ffffff', '#101827'] },
  { id: 'rhine-red-black', name: 'Rhine Red & Black', swatches: ['#d71920', '#09090b', '#f8fafc'] },
  { id: 'blancos-white', name: 'Blancos White', swatches: ['#101b3d', '#c9a227', '#f8f5e9'] },
  { id: 'blaugrana-night', name: 'Blaugrana Night', swatches: ['#143c8c', '#8a1538', '#d9a51c'] },
  { id: 'colchonero', name: 'Colchonero', swatches: ['#d71920', '#ffffff', '#102a56'] },
  { id: 'gunners-red', name: 'Gunners Red', swatches: ['#db0007', '#ffffff', '#14213d'] },
  { id: 'manchester-sky', name: 'Manchester Sky', swatches: ['#075985', '#7dd3fc', '#ffffff'] },
  { id: 'mersey-red', name: 'Mersey Red', swatches: ['#c8102e', '#ffffff', '#171717'] },
  { id: 'vecchia-signora', name: 'Vecchia Signora', swatches: ['#18181b', '#ffffff', '#a1a1aa'] },
  { id: 'nerazzurro-night', name: 'Nerazzurro Night', swatches: ['#1261c9', '#05070b', '#60a5fa'] },
  {
    id: 'milano-red-black',
    name: 'Milano Red & Black',
    swatches: ['#a50e2d', '#09090b', '#d4d4d8'],
  },
  {
    id: 'la-lupa-red-gold',
    name: 'La Lupa Red & Gold',
    swatches: ['#8e1f2d', '#e3b341', '#18181b'],
  },
] as const;

export function resolveThemeId(theme: string | null | undefined): ThemeId {
  return themeIds.includes(theme as ThemeId) ? (theme as ThemeId) : defaultThemeId;
}

export function applyTheme(theme: string | null | undefined): ThemeId {
  const resolved = resolveThemeId(theme);
  document.documentElement.dataset.theme = resolved;
  return resolved;
}
