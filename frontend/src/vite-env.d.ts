/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_DONATIONS_ENABLED?: string;
  readonly VITE_KOFI_URL?: string;
  readonly VITE_IMPRESSUM_ENABLED?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
