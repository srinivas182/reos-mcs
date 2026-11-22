<?php

declare(strict_types=1);

namespace App\Content\Scanning;

/**
 * ClamAV over its network protocol (clamd INSTREAM): the file is streamed to
 * the scanner in chunks and never written anywhere else.
 */
final readonly class ClamAvScanner implements FileScanner
{
    private const CHUNK = 1024 * 1024;

    public function __construct(private string $host, private int $port, private int $timeout) {}

    public function scan($stream): ScanResult
    {
        $socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $code, $message, 5);

        if ($socket === false) {
            return ScanResult::Unavailable;
        }

        try {
            stream_set_timeout($socket, $this->timeout);
            fwrite($socket, "zINSTREAM\0");

            while (! feof($stream)) {
                $chunk = fread($stream, self::CHUNK);

                if ($chunk === false) {
                    return ScanResult::Unavailable;
                }

                if ($chunk === '') {
                    continue;
                }

                fwrite($socket, pack('N', strlen($chunk)).$chunk);
            }

            fwrite($socket, pack('N', 0));
            $reply = trim((string) stream_get_contents($socket), "\0\n ");
        } finally {
            fclose($socket);
        }

        return self::verdict($reply);
    }

    /** "stream: OK" is clean; "stream: <name> FOUND" is infected; anything else means try again. */
    public static function verdict(string $reply): ScanResult
    {
        return match (true) {
            str_ends_with($reply, ': OK') => ScanResult::Clean,
            str_ends_with($reply, ' FOUND') => ScanResult::Infected,
            default => ScanResult::Unavailable,
        };
    }
}
