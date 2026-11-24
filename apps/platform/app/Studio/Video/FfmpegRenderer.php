<?php

declare(strict_types=1);

namespace App\Studio\Video;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Renders studio videos with ffmpeg in the queue worker (B12.2, ADR 0051).
 *
 * Each scene becomes a short segment of the same shape (1080 × 1920,
 * 30 fps, H.264 and AAC): a still drifts in or out slowly; a clip is
 * trimmed, cropped to fill the frame and has its pauses taken out; the
 * scene's caption sits on top. The segments are joined, then the frame
 * (brand band with the agent and broker), the hook for the first two
 * seconds, a fade and the music under the clips' own sound go on in one
 * last pass. Encoding uses the veryfast preset: a 30-second video takes
 * well under a minute on one worker.
 *
 * @phpstan-import-type Plan from VideoRenderer
 * @phpstan-import-type PlanScene from VideoRenderer
 * @phpstan-import-type Rendered from VideoRenderer
 * @phpstan-import-type Probe from VideoRenderer
 */
final readonly class FfmpegRenderer implements VideoRenderer
{
    private const FPS = 30;

    /** @var list<string> */
    private const VIDEO = ['-c:v', 'libx264', '-preset', 'veryfast', '-crf', '22', '-pix_fmt', 'yuv420p', '-r', '30'];

    /** @var list<string> */
    private const AUDIO = ['-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-ac', '2'];

    public function __construct(private string $ffmpeg = 'ffmpeg', private string $ffprobe = 'ffprobe', private int $timeout = 600) {}

    public function engine(): string
    {
        return 'ffmpeg';
    }

    public function render(array $plan, string $dir): array
    {
        $list = [];

        foreach ($plan['scenes'] as $i => $scene) {
            $segment = "{$dir}/segment-{$i}.mp4";
            $this->segment($scene, $segment, $i);
            $list[] = "file '".str_replace("'", "'\\''", $segment)."'";
        }

        if ($list === []) {
            throw new RuntimeException('A video needs at least one scene.');
        }

        file_put_contents("{$dir}/segments.txt", implode("\n", $list)."\n");
        $this->run([$this->ffmpeg, '-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', "{$dir}/segments.txt", '-c', 'copy', "{$dir}/joined.mp4"]);
        $seconds = $this->probe("{$dir}/joined.mp4")['seconds'] ?? array_sum(array_column($plan['scenes'], 'seconds'));

        $this->finish($plan, $dir, $seconds);
        $this->run([$this->ffmpeg, '-y', '-loglevel', 'error', '-ss', (string) min(0.5, $seconds / 2), '-i', "{$dir}/video.mp4", '-frames:v', '1', '-q:v', '3', "{$dir}/poster.jpg"]);

        return ['video' => "{$dir}/video.mp4", 'poster' => "{$dir}/poster.jpg", 'seconds' => round($seconds, 1)];
    }

    public function probe(string $file): array
    {
        $process = new Process([$this->ffprobe, '-v', 'error', '-show_entries', 'stream=codec_type:format=duration', '-of', 'json', $file]);
        $process->setTimeout(30)->run();
        $info = json_decode($process->getOutput(), true);

        if (! $process->isSuccessful() || ! is_array($info)) {
            return ['seconds' => null, 'sound' => false];
        }

        $duration = is_array($info['format'] ?? null) && is_numeric($info['format']['duration'] ?? null) ? (float) $info['format']['duration'] : null;
        $sound = false;

        foreach (is_array($info['streams'] ?? null) ? $info['streams'] : [] as $stream) {
            $sound = $sound || (is_array($stream) && ($stream['codec_type'] ?? null) === 'audio');
        }

        return ['seconds' => $duration, 'sound' => $sound];
    }

    /** @param  PlanScene  $scene */
    private function segment(array $scene, string $out, int $index): void
    {
        $seconds = sprintf('%.2F', $scene['seconds']);
        $args = [$this->ffmpeg, '-y', '-loglevel', 'error'];
        $sound = false;

        if ($scene['kind'] === 'image') {
            $frames = (int) round($scene['seconds'] * self::FPS);
            // Every other still drifts out instead of in.
            $zoom = $index % 2 === 0 ? 'min(zoom+0.0006,1.1)' : 'if(eq(on,0),1.1,max(zoom-0.0006,1))';
            $args = [...$args, '-i', $scene['file']];
            $video = "[0:v]zoompan=z='{$zoom}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d={$frames}:s=1080x1920:fps=30,setsar=1";
        } else {
            $args = [...$args, '-ss', sprintf('%.2F', $scene['start']), '-t', $seconds, '-i', $scene['file']];
            $sound = $scene['sound'];
            $video = '[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=30,setsar=1';
        }

        $inputs = 1;
        $caption = null;
        $silence = 0;

        if ($scene['caption'] !== null) {
            $args = [...$args, '-i', $scene['caption']];
            $caption = $inputs++;
        }

        if (! $sound) {
            $args = [...$args, '-f', 'lavfi', '-t', $seconds, '-i', 'anullsrc=r=44100:cl=stereo'];
            $silence = $inputs;
        }

        $audio = $sound ? '[0:a]aresample=44100' : "[{$silence}:a]anull";

        if ($sound) {
            $keep = Pauses::keep($this->silences($scene), $scene['seconds']);

            if (count($keep) > 1 || $keep[0][0] > 0.0 || $keep[0][1] < $scene['seconds'] - 0.05) {
                $select = Pauses::expression($keep);
                $video .= ",select={$select},setpts=N/30/TB";
                $audio .= ",aselect={$select},asetpts=N/SR/TB";
            }
        }

        $graph = "{$video}[bg];".($caption !== null ? "[bg][{$caption}:v]overlay=0:0,format=yuv420p[v];" : '[bg]format=yuv420p[v];')."{$audio}[a]";
        $this->run([...$args, '-filter_complex', $graph, '-map', '[v]', '-map', '[a]', ...($scene['kind'] === 'image' ? ['-t', $seconds] : ['-shortest']), ...self::VIDEO, ...self::AUDIO, $out]);
    }

    /**
     * The frame, the hook, the fades and the music, in one pass.
     *
     * @param  Plan  $plan
     */
    private function finish(array $plan, string $dir, float $seconds): void
    {
        $args = [$this->ffmpeg, '-y', '-loglevel', 'error', '-i', "{$dir}/joined.mp4", '-loop', '1', '-i', $plan['frame']];
        $next = 2;
        $hook = null;

        if ($plan['hook'] !== null) {
            $args = [...$args, '-loop', '1', '-i', $plan['hook']];
            $hook = $next++;
        }

        $expression = Music::expression($plan['music']);
        $music = null;

        if ($expression !== null) {
            $args = [...$args, '-f', 'lavfi', '-t', sprintf('%.2F', $seconds), '-i', "aevalsrc='{$expression}|{$expression}':s=44100:c=stereo"];
            $music = $next;
        }

        $out = sprintf('%.2F', max(0.0, $seconds - 0.6));
        $end = sprintf('%.2F', max(0.0, $seconds - 1.0));
        $graph = '[0:v][1:v]overlay=0:0:shortest=1[framed];';
        $graph .= $hook !== null ? sprintf("[framed][%d:v]overlay=0:0:shortest=1:enable='lt(t,%.2F)'[hooked];", $hook, $plan['hook_seconds']) : '[framed]null[hooked];';
        $graph .= "[hooked]fade=t=in:st=0:d=0.4,fade=t=out:st={$out}:d=0.6,format=yuv420p[v];";
        $graph .= $music !== null
            ? "[{$music}:a]volume=0.5[m];[0:a][m]amix=inputs=2:duration=first:dropout_transition=0:normalize=0,afade=t=in:st=0:d=0.5,afade=t=out:st={$end}:d=1[a]"
            : "[0:a]afade=t=out:st={$end}:d=1[a]";

        $this->run([...$args, '-filter_complex', $graph, '-map', '[v]', '-map', '[a]', '-t', sprintf('%.2F', $seconds), ...self::VIDEO, ...self::AUDIO, '-movflags', '+faststart', "{$dir}/video.mp4"]);
    }

    /** @param  PlanScene  $scene */
    private function silences(array $scene): string
    {
        $process = new Process([$this->ffmpeg, '-hide_banner', '-nostats', '-ss', sprintf('%.2F', $scene['start']), '-t', sprintf('%.2F', $scene['seconds']), '-i', $scene['file'], '-vn', '-af', 'silencedetect=noise=-35dB:d='.Pauses::MIN_PAUSE, '-f', 'null', '-']);
        $process->setTimeout(120)->run();

        return $process->getErrorOutput();
    }

    /** @param  list<string>  $command */
    private function run(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout($this->timeout)->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('ffmpeg failed: '.mb_substr(trim($process->getErrorOutput()), -500));
        }
    }
}
