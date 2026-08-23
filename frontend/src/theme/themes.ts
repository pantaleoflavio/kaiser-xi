export const themeIds = [
  'imperial-crimson',
  'golden-wall',
  'royal-standard',
  'northern-flame',
  'white-crown',
  'catalan-night',
  'red-stripes',
  'london-red',
  'sky-kingdom',
  'mersey-red',
  'black-crown',
  'nerazzurro-night',
  'rossonero',
  'imperial-burgundy',
] as const;

export type ThemeId = (typeof themeIds)[number];

export type Theme = {
  id: ThemeId;
  name: string;
  swatches: readonly [string, string, string];
};

export const defaultThemeId: ThemeId = 'imperial-crimson';

export const themes: readonly Theme[] = [
  { id: 'imperial-crimson', name: 'Imperial Crimson', swatches: ['#be123c', '#fbbf24', '#18181b'] },
  { id: 'golden-wall', name: 'Golden Wall', swatches: ['#a16207', '#facc15', '#1c1917'] },
  { id: 'royal-standard', name: 'Royal Standard', swatches: ['#1d4ed8', '#fbbf24', '#111827'] },
  { id: 'northern-flame', name: 'Northern Flame', swatches: ['#c2410c', '#38bdf8', '#172554'] },
  { id: 'white-crown', name: 'White Crown', swatches: ['#334155', '#e2e8f0', '#0f172a'] },
  { id: 'catalan-night', name: 'Catalan Night', swatches: ['#7e22ce', '#f59e0b', '#172554'] },
  { id: 'red-stripes', name: 'Red Stripes', swatches: ['#b91c1c', '#f8fafc', '#1e293b'] },
  { id: 'london-red', name: 'London Red', swatches: ['#be123c', '#f8fafc', '#172554'] },
  { id: 'sky-kingdom', name: 'Sky Kingdom', swatches: ['#0369a1', '#7dd3fc', '#172554'] },
  { id: 'mersey-red', name: 'Mersey Red', swatches: ['#b91c1c', '#2dd4bf', '#1c1917'] },
  { id: 'black-crown', name: 'Black Crown', swatches: ['#3f3f46', '#facc15', '#09090b'] },
  { id: 'nerazzurro-night', name: 'Nerazzurro Night', swatches: ['#1d4ed8', '#38bdf8', '#020617'] },
  { id: 'rossonero', name: 'Rossonero', swatches: ['#b91c1c', '#a1a1aa', '#09090b'] },
  {
    id: 'imperial-burgundy',
    name: 'Imperial Burgundy',
    swatches: ['#9f1239', '#f59e0b', '#2e1065'],
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
