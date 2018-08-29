<?php
declare(strict_types = 1);
namespace TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Snapshot;

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

class DatabaseSnapshot
{
    /**
     * Data up to 1 MiB is kept in memory
     */
    private const VALUE_IN_MEMORY_THRESHOLD = 1024**2;

    /**
     * @var self
     */
    private static $instance;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $identifier;

    /**
     * @var string
     */
    private $snapshotPath;

    /**
     * @var array
     */
    private $inMemoryImport;

    /**
     * @param string $path
     * @param string $identifier
     * @return self
     */
    public static function initialize(string $path, string $identifier): self
    {
        if (self::$instance === null) {
            self::$instance = new self($path, $identifier);
            return self::$instance;
        }
        throw new \LogicException(
            'Snapshot can only be initialized once',
            1535487361
        );
    }

    /**
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance !== null) {
            self::assertPath(self::$instance->path);
            return self::$instance;
        }
        throw new \LogicException(
            'Snapshot needs to be initialized first',
            1535487361
        );
    }

    /**
     * @return bool
     */
    public static function destroy(): bool
    {
        if (self::$instance === null) {
            return false;
        }
        self::$instance->purge();
        self::$instance = null;
        return true;
    }

    /**
     * @param string $path
     * @param string $identifier
     */
    private function __construct(string $path, string $identifier)
    {
        self::assertPath($path);

        $this->identifier = $identifier;
        $this->path = rtrim($path, '/');
        $this->snapshotPath = $this->buildPath($identifier);
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return is_file($this->snapshotPath);
    }

    /**
     * @return bool
     */
    public function purge(): bool
    {
        unset($this->inMemoryImport);
        return unlink($this->snapshotPath);
    }

    /**
     * @param DatabaseAccessor $accessor
     */
    public function create(DatabaseAccessor $accessor)
    {
        $export = $accessor->export();
        $serialized = json_encode($export);
        // It's not the exact consumption due to serialization literals... fine
        if (strlen($serialized) <= self::VALUE_IN_MEMORY_THRESHOLD) {
            $this->inMemoryImport = $export;
        }

        file_put_contents($this->snapshotPath, $serialized);
    }

    /**
     * @param DatabaseAccessor $accessor
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function restore(DatabaseAccessor $accessor)
    {
        $import = $this->inMemoryImport ?? json_decode(
            file_get_contents($this->snapshotPath),
            true
        );

        if (!is_array($import)) {
            throw new \RuntimeException(
                'Invalid import data',
                1535487372
            );
        }

        $accessor->import($import);
    }

    /**
     * @param string $identifier
     * @return string
     */
    private function buildPath(string $identifier): string
    {
        return sprintf(
            '%s/%s.snapshot',
            $this->path,
            $identifier
        );
    }

    private static function assertPath(string $path)
    {
        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            throw new \RuntimeException(
                sprintf('Snapshot path "%s" not available', $path),
                1535487371
            );
        }
    }
}
