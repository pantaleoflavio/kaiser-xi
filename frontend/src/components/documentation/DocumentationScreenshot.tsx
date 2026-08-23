import { useEffect, useState } from 'react';

type DocumentationScreenshotProps = {
  src: string;
  alt: string;
  caption?: string;
};

export function DocumentationScreenshot({ src, alt, caption }: DocumentationScreenshotProps) {
  const [missing, setMissing] = useState(false);

  useEffect(() => setMissing(false), [src]);

  if (missing) {
    return null;
  }

  return (
    <figure className="overflow-hidden rounded-2xl border border-theme-border bg-theme-muted-surface">
      <img
        alt={alt}
        className="h-auto w-full object-contain"
        loading="lazy"
        onError={() => setMissing(true)}
        src={src}
      />
      {caption && (
        <figcaption className="border-t border-theme-border px-4 py-3 text-sm text-theme-muted">
          {caption}
        </figcaption>
      )}
    </figure>
  );
}
