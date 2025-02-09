<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests\Unit;

use PHPUnit\Framework\TestCase;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Exceptions\RuntimeException;
use Zaphyr\HttpMessage\Stream;

class StreamTest extends TestCase
{
    /**
     * @var string|null
     */
    protected string|null $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = null;
    }

    protected function tearDown(): void
    {
        if (is_string($this->tempFile) && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    /* -------------------------------------------------
     * CONSTRUCTOR
     * -------------------------------------------------
     */

    public function testConstructorWithResource(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'r+');

        self::assertSame('foo', (new Stream($resource))->__toString());
    }

    public function testConstructorWithStringResource(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        $stream = new Stream($this->tempFile);

        $resource = fopen($this->tempFile, 'r+');
        fwrite($resource, 'foo');

        $stream->rewind();

        self::assertSame('foo', (string)$stream);
    }

    public function testConstructorThrowsExceptionOnInvalidResource(): void
    {
        $this->expectException(RuntimeException::class);

        new Stream('nope');
    }

    public function testConstructorThrowsExceptionOnInvalidStreamResource(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Stream(['nope']);
    }

    /* -------------------------------------------------
     * TO STRING
     * -------------------------------------------------
     */

    public function testToString(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'r');
        $stream = $this
            ->getMockBuilder(Stream::class)
            ->setConstructorArgs([$resource])
            ->onlyMethods(['isSeekable'])
            ->getMock();
        $stream->expects(self::once())
            ->method('isSeekable')
            ->willReturn(false);

        self::assertSame('foo', (string)$stream);
    }

    public function testToStringReturnsFullContentsOfStream(): void
    {
        $stream = new Stream('php://memory', 'wb+');
        $stream->write($message = 'foo');

        self::assertSame($message, (string)$stream);
    }

    public function testToStringReturnsEmptyStringWhenStreamIsNotReadable(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $stream = new Stream($this->tempFile, 'w');

        self::assertSame('', (string)$stream);
    }

    public function testToStringReturnsEmptyStringWhenGetContentsThrowsException(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs([$this->tempFile, 'r'])
            ->onlyMethods(['getContents'])
            ->getMock();
        $stream->expects(self::once())
            ->method('getContents')
            ->willThrowException(new RuntimeException());

        self::assertSame('', (string)$stream);
    }

    /* -------------------------------------------------
     * CLOSE
     * -------------------------------------------------
     */

    public function testCloseResource(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        $resource = fopen($this->tempFile, 'wb+');
        (new Stream($resource))->close();

        self::assertFalse(is_resource($resource));
    }

    public function testCloseUnsetsResource(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        $stream = new Stream(fopen($this->tempFile, 'wb+'));
        $stream->close();

        self::assertNull($stream->detach());
    }

    public function testCloseDoesNothingAfterDetach(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = new Stream($resource);
        $detached = $stream->detach();
        $stream->close();

        self::assertIsResource($detached);
        self::assertSame($resource, $detached);
    }

    /* -------------------------------------------------
     * DETACH
     * -------------------------------------------------
     */

    public function testDetachReturnsResource(): void
    {
        $resource = fopen('php://memory', 'wb+');

        self::assertSame($resource, (new Stream($resource))->detach());
    }

    /* -------------------------------------------------
     * GET SIZE
     * -------------------------------------------------
     */

    public function testGetSize(): void
    {
        $resource = fopen(__FILE__, 'r');
        $expected = fstat($resource);

        self::assertSame($expected['size'], (new Stream($resource))->getSize());
    }

    public function testGetSizeIsNullWhenNoResource(): void
    {
        $stream = new Stream('php://memory', 'wb+');
        $stream->detach();

        self::assertNull($stream->getSize());
    }

    public function testGetSizeIsNullForPhpInputStreams(): void
    {
        self::assertNull((new Stream(fopen('php://input', 'r')))->getSize());
    }

    /* -------------------------------------------------
     * TELL
     * -------------------------------------------------
     */

    public function testTell(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        fseek($resource, 2);

        self::assertSame(2, (new Stream($resource))->tell());
    }

    public function testTellThrowsExceptionWhenResourceIsDetached(): void
    {
        $this->expectException(RuntimeException::class);

        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = new Stream($resource);
        fseek($resource, 2);
        $stream->detach();
        $stream->tell();
    }

    /* -------------------------------------------------
     * EOF
     * -------------------------------------------------
     */

    public function testEofReturnsFalseWhenNotAtEndOfStream(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        fseek($resource, 2);

        self::assertFalse((new Stream($resource))->eof());
    }

    public function testEofReturnsTrueWhenAtEndOfStream(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');

        while (!feof($resource)) {
            fread($resource, 4096);
        }

        self::assertTrue((new Stream($resource))->eof());
    }

    public function testEofReturnsTrueWhenStreamIsDetached(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = new Stream($resource);
        fseek($resource, 2);
        $stream->detach();

        self::assertTrue($stream->eof());
    }

    /* -------------------------------------------------
     * IS SEEKABLE
     * -------------------------------------------------
     */

    public function testIsSeekableReturnsTrueForReadableStreams(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');

        self::assertTrue((new Stream($resource))->isSeekable());
    }

    public function testIsSeekableReturnsFalseForDetachedStreams(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = new Stream($resource);
        $stream->detach();

        self::assertFalse($stream->isSeekable());
    }

    /* -------------------------------------------------
     * SEEK
     * -------------------------------------------------
     */

    public function testSeek(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = new Stream($resource);
        $stream->seek(2);

        self::assertSame(2, $stream->tell());
    }

    public function testSeekThrowsExceptionWhenStreamIsDetached(): void
    {
        $this->expectException(RuntimeException::class);

        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = new Stream($resource);
        $stream->detach();
        $stream->seek(2);
    }

    public function testSeekThrowsExceptionWhenStreamIsNotSeekable(): void
    {
        $this->expectException(RuntimeException::class);

        $stream = new Stream('php://memory', 'wb+');
        $stream->seek(-1);
    }

    public function testSeekThrowsExceptionWhenStreamIsClosed(): void
    {
        $this->expectException(RuntimeException::class);

        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = $this
            ->getMockBuilder(Stream::class)
            ->setConstructorArgs([$resource])
            ->onlyMethods(['isSeekable'])
            ->getMock();
        $stream->expects(self::once())
            ->method('isSeekable')
            ->willReturn(false);
        $stream->seek(2);
    }

    /* -------------------------------------------------
     * REWIND
     * -------------------------------------------------
     */

    public function testRewind(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = new Stream($resource);
        $stream->seek(2);
        $stream->rewind();

        self::assertSame(0, $stream->tell());
    }

    /* -------------------------------------------------
     * IS WRITABLE
     * -------------------------------------------------
     */

    public function testIsWritable(): void
    {
        self::assertTrue((new Stream("php://temp", "r+b"))->isWritable());
    }

    public function testIsWritableReturnsFalseIfStreamIsNotWritable(): void
    {
        self::assertFalse((new Stream('php://memory', 'r'))->isWritable());
    }

    public function testIsWritableReturnsFalseWhenStreamIsDetached(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = new Stream($resource);
        $stream->detach();

        self::assertFalse($stream->isWritable());
    }

    /**
     * @param string $mode
     * @param bool   $fileShouldExist
     * @param bool   $flag
     *
     * @dataProvider isWritableDataProvider
     */
    public function testIsWritableReturnsCorrectFlagForMode(string $mode, bool $fileShouldExist, bool $flag): void
    {
        if ($fileShouldExist) {
            $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
            file_put_contents($this->tempFile, 'foo');
        } else {
            $this->tempFile = $this->findNonExistentTempFile();
        }

        $resource = fopen($this->tempFile, $mode);

        self::assertSame($flag, (new Stream($resource))->isWritable());
    }

    /**
     * @return array<int, array<int, string|bool>>
     */
    public static function isWritableDataProvider(): array
    {
        return [
            ['a', true, true],
            ['a+', true, true],
            ['a+b', true, true],
            ['ab', true, true],
            ['c', true, true],
            ['c+', true, true],
            ['c+b', true, true],
            ['cb', true, true],
            ['r', true, false],
            ['r+', true, true],
            ['r+b', true, true],
            ['rb', true, false],
            ['rw', true, true],
            ['w', true, true],
            ['w+', true, true],
            ['w+b', true, true],
            ['wb', true, true],
            ['x', false, true],
            ['x+', false, true],
            ['x+b', false, true],
            ['xb', false, true],
        ];
    }

    private function findNonExistentTempFile(): string
    {
        while (true) {
            $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'foo' . uniqid('', true);
            if (!file_exists(sys_get_temp_dir() . $tempFile)) {
                break;
            }
        }

        return $tempFile;
    }

    /* -------------------------------------------------
     * WRITE
     * -------------------------------------------------
     */

    public function testWriteThrowsExceptionWhenStreamIsDetached(): void
    {
        $this->expectException(RuntimeException::class);

        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = new Stream($resource);
        $stream->detach();
        $stream->write('bar');
    }

    public function testWriteThrowsExceptionWhenStreamIsNotWritable(): void
    {
        $this->expectException(RuntimeException::class);

        (new Stream('php://memory', 'r'))->write('bar');
    }

    /* -------------------------------------------------
     * IS READABLE
     * -------------------------------------------------
     */

    public function testIsReadableReturnsFalseIfStreamIsNotReadable(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');

        self::assertFalse((new Stream($this->tempFile, 'w'))->isReadable());
    }

    /**
     * @param string $mode
     * @param bool   $fileShouldExist
     * @param bool   $flag
     *
     * @dataProvider isReadableDataProvider
     */
    public function testIsReadableReturnsCorrectFlagForMode(string $mode, bool $fileShouldExist, bool $flag): void
    {
        if ($fileShouldExist) {
            $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
            file_put_contents($this->tempFile, 'foo');
        } else {
            $this->tempFile = $this->findNonExistentTempFile();
        }

        $resource = fopen($this->tempFile, $mode);

        self::assertSame($flag, (new Stream($resource))->isReadable());
    }

    public function testIsReadableReturnsFalseWhenStreamIsDetached(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = new Stream($resource);
        $stream->detach();

        self::assertFalse($stream->isReadable());
    }

    /**
     * @return array<int, array<int, string|bool>>
     */
    public static function isReadableDataProvider(): array
    {
        return [
            ['a', true, false],
            ['a+', true, true],
            ['a+b', true, true],
            ['ab', true, false],
            ['c', true, false],
            ['c+', true, true],
            ['c+b', true, true],
            ['cb', true, false],
            ['r', true, true],
            ['r+', true, true],
            ['r+b', true, true],
            ['rb', true, true],
            ['rw', true, true],
            ['w', true, false],
            ['w+', true, true],
            ['w+b', true, true],
            ['wb', true, false],
            ['x', false, false],
            ['x+', false, true],
            ['x+b', false, true],
            ['xb', false, false],
        ];
    }

    /* -------------------------------------------------
     * READ
     * -------------------------------------------------
     */

    public function testReadReturnsEmptyStringWhenAtEndOfFile(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'r');

        while (!feof($resource)) {
            fread($resource, 4096);
        }

        self::assertSame('', (new Stream($resource))->read(4096));
    }

    public function testReadThrowsExceptionWhenStreamIsDetached(): void
    {
        $this->expectException(RuntimeException::class);

        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'r');
        $stream = new Stream($resource);
        $stream->detach();
        $stream->read(4096);
    }

    public function testReadThrowsExceptionWhenStreamIsClosed(): void
    {
        $this->expectException(RuntimeException::class);

        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'wb+');
        $stream = $this
            ->getMockBuilder(Stream::class)
            ->setConstructorArgs([$resource])
            ->onlyMethods(['isReadable'])
            ->getMock();
        $stream->expects(self::once())
            ->method('isReadable')
            ->willReturn(false);
        $stream->read(0);
    }

    /* -------------------------------------------------
     * GET CONTENTS
     * -------------------------------------------------
     */

    public function testGetContents(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        $resource = fopen($this->tempFile, 'r+');
        $stream = new Stream($resource);
        fwrite($resource, 'foo');
        $stream->rewind();

        self::assertSame('foo', $stream->getContents());
    }

    public function testGetContentsThrowsExceptionIfStreamIsNotReadable(): void
    {
        $this->expectException(RuntimeException::class);

        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        file_put_contents($this->tempFile, 'foo');
        $resource = fopen($this->tempFile, 'w');
        $stream = new Stream($resource);
        $stream->getContents();
    }

    public function testGetContentsReturnStreamContentsFromCurrentPointer(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        $resource = fopen($this->tempFile, 'r+');
        $stream = new Stream($resource);
        fwrite($resource, 'FooBar');
        $stream->seek(3);

        self::assertSame('Bar', $stream->getContents());
    }

    public function testGetContentsThrowsExceptionWhenResourceIsDetached(): void
    {
        $this->expectException(RuntimeException::class);

        $stream = new Stream('php://memory', 'wb+');
        $stream->detach();

        self::assertNull($stream->getContents());
    }

    /* -------------------------------------------------
     * GET METADATA
     * -------------------------------------------------
     */

    public function testGetMetadata(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        $resource = fopen($this->tempFile, 'r+');

        self::assertSame(stream_get_meta_data($resource), (new Stream($resource))->getMetadata());
    }

    public function testGetMetadataReturnsDataForSpecifiedKey(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        $resource = fopen($this->tempFile, 'r+');
        $metadata = stream_get_meta_data($resource);

        self::assertSame($metadata['uri'], (new Stream($resource))->getMetadata('uri'));
    }

    public function testGetMetadataReturnsNullIfNoDataExistsForKey(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'zaphyr');
        $resource = fopen($this->tempFile, 'r+');

        self::assertNull((new Stream($resource))->getMetadata('nope'));
    }

    public function testGetMetadataThrowsExceptionWhenResourceIsDetached(): void
    {
        $this->expectException(RuntimeException::class);

        $stream = new Stream('php://memory', 'wb+');
        $stream->detach();

        self::assertNull($stream->getMetadata());
    }
}
