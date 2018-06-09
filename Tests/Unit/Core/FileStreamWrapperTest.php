<?php
declare(strict_types = 1);
namespace TYPO3\TestingFramework\Core\Tests\Unit;

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

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\visitor\vfsStreamStructureVisitor;
use TYPO3\TestingFramework\Core\FileStreamWrapper;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class FileStreamWrapperTest extends UnitTestCase
{
    /**
     * @test
     */
    public function pathsAreOverlaidAndFinalDirectoryStructureCanBeQueried(): void
    {
        $root = vfsStream::setup('root');
        $subfolder = vfsStream::newDirectory('fileadmin');
        $root->addChild($subfolder);
        // Load fixture files and folders from disk
        vfsStream::copyFromFileSystem(__DIR__ . '/Fixtures/TestDir', $subfolder, 1024*1024);
        FileStreamWrapper::init(__DIR__);
        FileStreamWrapper::registerOverlayPath('fileadmin', 'vfs://root/fileadmin', false);

        // Use file functions as normal
        mkdir(__DIR__ . '/fileadmin/test/');
        $file = __DIR__ . '/fileadmin/test/Foo.bar';
        file_put_contents($file, 'Baz');
        $content = file_get_contents($file);
        $this->assertSame('Baz', $content);

        $expectedFileSystem = [
            'root' => [
                'fileadmin' => [
                    'test' => [
                        'Foo.bar' => 'Baz'
                    ],
                    'testfile.txt' => 'some content',
                ],
            ],
        ];
        $this->assertEquals($expectedFileSystem, vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure());
        FileStreamWrapper::destroy();
    }

    /**
     * @test
     */
    public function windowsPathsCanBeProcessed(): void
    {
        $cRoot = 'C:\\Windows\\Root\\Path\\';
        vfsStream::setup('root');
        FileStreamWrapper::init($cRoot);
        FileStreamWrapper::registerOverlayPath('fileadmin', 'vfs://root/fileadmin');

        touch($cRoot . 'fileadmin\\someFile.txt');
        $expectedFileStructure = [
            'root' => [
                'fileadmin' => ['someFile.txt' => null],
            ],
        ];

        $this->assertEquals($expectedFileStructure, vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure());
        FileStreamWrapper::destroy();
    }
}
