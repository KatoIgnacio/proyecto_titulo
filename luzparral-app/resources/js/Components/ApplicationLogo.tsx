import { ImgHTMLAttributes } from 'react';

type ApplicationLogoProps = ImgHTMLAttributes<HTMLImageElement> & {
    withBackground?: boolean;
};

export default function ApplicationLogo({ withBackground = false, alt = 'SIGCEL Luzparral', ...props }: ApplicationLogoProps) {
    return (
        <img
            {...props}
            src={withBackground ? '/images/logo-sigcel-fondo-blanco.png' : '/images/logo-sigcel-transparente.png'}
            alt={alt}
        />
    );
}
