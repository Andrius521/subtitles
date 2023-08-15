<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\UserException;

class SrtConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return preg_match('/^0*\d?\R(\d{1,2}:\d{2}:\d{2},\d{1,3}\s*-->\s*\d{1,2}:\d{2}:\d{2},\d{1,3})\R(.+)$/m', $file_content) === 1;
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($file_content)
    {
        $internal_format = []; // array - where file content will be stored

        $lines = explode("\n", trim($file_content));
        $lines = array_map('trim', $lines);
        $tmp_content = implode("\n", $lines);
        unset($lines);


        $blocks = explode("\n\n", $tmp_content); // each block contains: start and end times + text
        foreach ($blocks as $block) {
            preg_match('/(?<start>.*) *--> *(?<end>.*)\n(?<text>(\n*.*)*)/m', $block, $matches);

            // if block doesn't contain text (invalid srt file given)
            if (empty($matches)) {
                continue;
            }
            $lines = explode("\n", $matches['text']);
            $lines_array = array_map('strip_tags', $lines);
            $internal_format[] = [
                'start' => static::srtTimeToInternal($matches['start'], implode("\n", $lines)),
                'end' => static::srtTimeToInternal($matches['end'], implode("\n", $lines)),
                'lines' => $lines_array,
            ];
        }

        return $internal_format;
    }

    /**
     * Convert library's "internal format" (array) to file's content
     *
     * @param array $internal_format    Internal format
     * @return string                   Converted file content
     */
    public function internalFormatToFileContent(array $internal_format)
    {
        $file_content = '';

        foreach ($internal_format as $k => $block) {
            $nr = $k + 1;
            $start = static::internalTimeToSrt($block['start']);
            $end = static::internalTimeToSrt($block['end']);
            $lines = implode("\r\n", $block['lines']);

            $file_content .= $nr . "\r\n";
            $file_content .= $start . ' --> ' . $end . "\r\n";
            $file_content .= $lines . "\r\n";
            $file_content .= "\r\n";
        }

        $file_content = trim($file_content);

        return $file_content;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    /**
     * Convert .srt file format to internal time format (float in seconds)
     * Example: 00:02:17,440 -> 137.44
     *
     * @param $srt_time
     *
     * @return float
     */
    protected static function srtTimeToInternal($srt_time, string $lines)
    {
        $pattern = '/(\d{1,2}):(\d{2}):(\d{1,2})([:.,](\d{1,3}))?/m';
        if (preg_match($pattern, $srt_time, $matches)) {
            $hours = $matches[1];
            $minutes = $matches[2];
            $seconds = $matches[3];
            $milliseconds = isset($matches[5]) ? $matches[5] : "000";
        } else {
            throw new UserException("Can't parse timestamp: \"$srt_time\", near: $lines");
        }

        return $hours * 3600 + $minutes * 60 + $seconds + str_pad($milliseconds, 3, "0", STR_PAD_RIGHT) / 1000;
    }

    /**
     * Convert internal time format (float in seconds) to .srt time format
     * Example: 137.44 -> 00:02:17,440
     *
     * @param float $internal_time
     *
     * @return string
     */
    public static function internalTimeToSrt($internal_time)
    {
        $parts = explode('.', $internal_time); // 1.23
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 3) : 0; // 23

        $srt_time = gmdate("H:i:s", floor($whole)) . ',' . str_pad($decimal, 3, '0', STR_PAD_RIGHT);

        return $srt_time;
    }
}
