<?php

declare(strict_types=1);
namespace TYPO3\TestingFramework\Core\Functional\Framework\DataHandling;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Stream Filter for writing CSV files
 * Inspired by https://csv.thephpleague.com/9.0/interoperability/enclose-field/
 *
 * A unique sequence (e.g. contains new-line, tab, white space) is added to
 * relevant CSV field values in order to trigger enclosure in fputcsv. This stream
 * filter is taking care of removing that sequence again when acutally writing to stream.
 *
 * @deprecated Will be removed with core v12 compatible testing-framework.
 */
class CsvWriterStreamFilter extends \php_user_filter
{
    private const FILTERNAME = 'csv.typo3.testing-framework';

    /**
     * Registers stream filter
     */
    public static function register()
    {
        if (in_array(self::FILTERNAME, stream_get_filters(), true)) {
            return;
        }
        stream_filter_register(
            self::FILTERNAME,
            static::class
        );
    }

    /**
     * @param resource $stream
     * @param string $sequence
     */
    public static function apply($stream, string $sequence = null): \Closure
    {
        static::register();
        if ($sequence === null) {
            $sequence = "\t\x1e\x1f";
        }
        stream_filter_append(
            $stream,
            self::FILTERNAME,
            STREAM_FILTER_WRITE,
            ['sequence' => $sequence]
        );
        return static::buildModifier($sequence);
    }

    /**
     * @param string $sequence
     * @return \Closure
     */
    public static function buildModifier(string $sequence): \Closure
    {
        return function ($element) use ($sequence) {
            foreach ($element as &$value) {
                if (is_numeric($value) || $value === '') {
                    continue;
                }
                $value = $sequence . $value;
            }
            unset($value); // de-reference
            return $element;
        };
    }

    /**
     * @param resource $in
     * @param resource $out
     * @param int $consumed
     * @param bool $closing
     * @return int
     */
    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($resource = stream_bucket_make_writeable($in)) {
            $resource->data = str_replace(
                $this->params['sequence'],
                '',
                $resource->data
            );
            $consumed += $resource->datalen;
            stream_bucket_append($out, $resource);
        }
        return PSFS_PASS_ON;
    }
}
