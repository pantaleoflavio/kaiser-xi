import type { ComponentPropsWithoutRef } from 'react';

type KaiserXiLogoVariant = 'symbol' | 'horizontal' | 'full';

const logoAssets: Record<KaiserXiLogoVariant, { src: string; width: number; height: number }> = {
  symbol: { src: '/branding/kaiser-xi-symbol.png', width: 1271, height: 1238 },
  horizontal: { src: '/branding/kaiser-xi-horizontal.png', width: 2172, height: 724 },
  full: { src: '/branding/kaiser-xi-full.png', width: 2172, height: 724 },
};

type KaiserXiLogoProps = Omit<ComponentPropsWithoutRef<'img'>, 'src' | 'width' | 'height'> & {
  variant: KaiserXiLogoVariant;
};

export function KaiserXiLogo({ alt = 'Kaiser XI', variant, ...props }: KaiserXiLogoProps) {
  const asset = logoAssets[variant];

  return <img alt={alt} height={asset.height} src={asset.src} width={asset.width} {...props} />;
}
