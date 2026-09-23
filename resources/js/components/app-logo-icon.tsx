import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 34 34" xmlns="http://www.w3.org/2000/svg">
            <rect x="4.5" y="4.5" width="25" height="4.6" rx="2.3" />
            <rect x="4.5" y="14.7" width="17.7" height="4.6" rx="2.3" />
            <rect x="4.5" y="24.9" width="8.3" height="4.6" rx="2.3" />
        </svg>
    );
}
