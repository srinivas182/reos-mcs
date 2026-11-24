<?php

declare(strict_types=1);

namespace App\Studio\Video;

/**
 * REOS-made music for studio videos (B12.2, ADR 0051; screen 168: royalty
 * free only). The tracks are written as sound, not recorded, so REOS owns
 * them outright and no licence can lapse. A licensed library can join
 * them later behind the same names.
 *
 * - **calm:** slow swelling chords (C, A minor, F, G), four seconds each;
 * - **bright:** a light picked pattern over the same chords, two seconds each;
 * - **none:** only the clips' own sound.
 */
final class Music
{
    public const TRACKS = ['calm', 'bright', 'none'];

    /** The chords' root notes in hertz. */
    private const ROOTS = [261.63, 220.0, 174.61, 196.0];

    /** An ffmpeg aevalsrc expression for one channel, or null for no music. */
    public static function expression(string $track): ?string
    {
        $root = static fn (string $step): string => sprintf(
            'if(eq(%1$s,0),%2$.2F,if(eq(%1$s,1),%3$.2F,if(eq(%1$s,2),%4$.2F,%5$.2F)))',
            $step, ...self::ROOTS,
        );

        return match ($track) {
            // Each chord swells in and out over its four seconds, so changes never click.
            'calm' => 'st(0,'.$root('mod(floor(t/4),4)').');sin(PI*mod(t,4)/4)*(0.16*sin(2*PI*ld(0)*t)+0.11*sin(2*PI*ld(0)*1.26*t)+0.09*sin(2*PI*ld(0)*1.5*t)+0.07*sin(PI*ld(0)*t))',
            // Four notes a second, each fading fast, over a soft low note.
            'bright' => 'st(0,'.$root('mod(floor(t/2),4)').');st(1,if(eq(mod(floor(t*4),4),0),1,if(eq(mod(floor(t*4),4),1),1.26,if(eq(mod(floor(t*4),4),2),1.5,2))));0.22*exp(-7*mod(t,0.25))*sin(PI*mod(t,0.25)/0.25)*sin(2*PI*ld(0)*ld(1)*t)+0.08*sin(PI*mod(t,2)/2)*sin(PI*ld(0)*t)',
            default => null,
        };
    }
}
