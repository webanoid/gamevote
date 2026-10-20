import type { SVGProps } from 'react';
import Meeple from '@/components/meeple';

/** The GameVote logo: a meeple, the piece used for votes. */
export default function AppLogoIcon(props: SVGProps<SVGSVGElement>) {
    return <Meeple {...props} />;
}
