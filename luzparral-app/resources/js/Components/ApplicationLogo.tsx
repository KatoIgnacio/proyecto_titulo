import { ImgHTMLAttributes } from 'react';

type ApplicationLogoProps = ImgHTMLAttributes<HTMLImageElement> & {
    withBackground?: boolean;
};

export default function ApplicationLogo({ withBackground = false, alt = 'Sistema de contingencias Luzparral', ...props }: ApplicationLogoProps) {
    return (
        <img
            {...props}
            src={withBackground ? '/images/logo-sistema-fondo-blanco.png' : '/images/logo-sistema-transparente.png'}
            alt={alt}
        />
    );
}
