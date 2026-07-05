<?php

namespace App\Http\Traits;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a stored file honoring HTTP Range requests.
 *
 * iOS AVPlayer requires either a 206 with Content-Range or a 200 with an
 * explicit Content-Length; a length-less 200 fails playback outright
 * (CoreMediaErrorDomain -12939, Sentry BANDMATE-APP-2). Every response here
 * therefore advertises Accept-Ranges and carries a Content-Length.
 */
trait ServesByteRanges
{
    protected function streamWithByteRanges(
        Request $request,
        Filesystem $disk,
        string $path,
        array $headers,
    ): StreamedResponse|\Illuminate\Http\Response {
        $size = $disk->size($path);
        $headers['Accept-Ranges'] = 'bytes';

        $rangeHeader = $request->header('Range');

        if ($rangeHeader && preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
            $start = (int) $matches[1];
            $end   = $matches[2] !== '' ? (int) $matches[2] : $size - 1;
            $end   = min($end, $size - 1);

            if ($start > $end || $start >= $size) {
                return response('', 416, ['Content-Range' => "bytes */{$size}"]);
            }

            $length = $end - $start + 1;
            $stream = $disk->readStream($path);

            // Seek to start; for non-seekable streams (e.g. S3) read and discard.
            if ($start > 0) {
                if (stream_get_meta_data($stream)['seekable']) {
                    fseek($stream, $start);
                } else {
                    $skipped = 0;
                    while ($skipped < $start && !feof($stream)) {
                        $chunk = fread($stream, min(65536, $start - $skipped));
                        if ($chunk === false || $chunk === '') {
                            break;
                        }
                        $skipped += strlen($chunk);
                    }

                    // Stream ended before the range start: the stored size is
                    // out of sync with the actual object; the range is unservable.
                    if ($skipped < $start) {
                        fclose($stream);

                        return response('', 416, ['Content-Range' => "bytes */{$size}"]);
                    }
                }
            }

            return response()->stream(function () use ($stream, $length) {
                $remaining = $length;
                while ($remaining > 0 && !feof($stream)) {
                    $chunk = fread($stream, min(65536, $remaining));
                    echo $chunk;
                    $remaining -= strlen($chunk);
                    flush();
                }
                fclose($stream);
            }, 206, array_merge($headers, [
                'Content-Length' => $length,
                'Content-Range'  => "bytes {$start}-{$end}/{$size}",
            ]));
        }

        $stream = $disk->readStream($path);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, array_merge($headers, [
            'Content-Length' => $size,
        ]));
    }
}
