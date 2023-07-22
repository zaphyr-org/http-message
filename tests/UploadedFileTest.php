<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests;

use Generator;
use PHPUnit\Framework\TestCase;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Exceptions\RuntimeException;
use Zaphyr\HttpMessage\Stream;
use Zaphyr\HttpMessage\UploadedFile;

class UploadedFileTest extends TestCase
{
    /**
     * @var string|null
     */
    protected string|null $tempFile;

    public function setUp(): void
    {
        $this->tempFile = null;
    }

    public function tearDown(): void
    {
        if (is_string($this->tempFile) && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    /* -------------------------------------------------
     * CONSTRUCTOR
     * -------------------------------------------------
     */

    /**
     * @param mixed $streamOrFile
     *
     * @dataProvider invalidStreamsDataProvider
     */
    public function testConstructorThrowsExceptionOnInvalidStreamOrFile(mixed $streamOrFile): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UploadedFile($streamOrFile, 0, UPLOAD_ERR_OK);
    }

    /**
     * @param int $status
     *
     * @dataProvider invalidErrorStatusesDataProvider
     */
    public function testConstructorThrowsExceptionOnInvalidErrorStatus(int $status): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line */
        new UploadedFile(fopen('php://temp', 'wb+'), 0, $status);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function invalidStreamsDataProvider(): array
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'int' => [1],
            'float' => [1.1],
            'array' => [['filename']],
            'object' => [(object)['filename']],
        ];
    }

    /**
     * @return array<string, array<int>>
     */
    public static function invalidErrorStatusesDataProvider(): array
    {
        return [
            'negative' => [-1],
            'too-big' => [9],
        ];
    }

    /* -------------------------------------------------
     * GET STREAM
     * -------------------------------------------------
     */

    public function testGetStream(): void
    {
        $stream = new Stream('php://temp');

        self::assertSame($stream, (new UploadedFile($stream, 0, UPLOAD_ERR_OK))->getStream());
    }

    public function testGetStreamReturnsWrappedPhpStream(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertSame($stream, (new UploadedFile($stream, 0, UPLOAD_ERR_OK))->getStream()->detach());
    }

    /*public function testGetStreamReturnsStreamForFile(): void
    {
        $this->tempFile = $stream = (string)tempnam(sys_get_temp_dir(), 'zaphyr');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);
        $uploadStream = $upload->getStream();

        $property = new ReflectionProperty($uploadStream, 'stream');
        $property->setAccessible(true);

        self::assertSame($stream, $property->getValue($uploadStream));
    }*/

    public function testGetStreamCannotRetrieveMovedStream(): void
    {
        $this->expectException(RuntimeException::class);

        $stream = new Stream('php://temp', 'wb+');
        $stream->write('foo');
        $uploadedFile = new UploadedFile($stream, 0, UPLOAD_ERR_OK);
        $this->tempFile = $to = (string)tempnam(sys_get_temp_dir(), 'zaphyr');
        $uploadedFile->moveTo($to);

        self::assertFileExists($to);

        $uploadedFile->getStream();
    }

    /**
     * @param int $status
     *
     * @dataProvider nonOkErrorStatusDataProvider
     */
    public function testGetStreamThrowsExceptionWhenErrorStatusIsPresent(int $status): void
    {
        $this->expectException(RuntimeException::class);

        (new UploadedFile('not ok', 0, $status))->getStream();
    }

    /**
     * @param int $constant
     *
     * @dataProvider errorConstantsAndMessagesDataProvider
     */
    public function testGetStreamThrowsExceptionWhenUploadErrorDetected(int $constant): void
    {
        $this->expectException(RuntimeException::class);

        (new UploadedFile(__FILE__, 100, $constant))->getStream();
    }

    /**
     * @return array<string, array<int>>
     */
    public static function nonOkErrorStatusDataProvider(): array
    {
        return [
            'UPLOAD_ERR_INI_SIZE' => [UPLOAD_ERR_INI_SIZE],
            'UPLOAD_ERR_FORM_SIZE' => [UPLOAD_ERR_FORM_SIZE],
            'UPLOAD_ERR_PARTIAL' => [UPLOAD_ERR_PARTIAL],
            'UPLOAD_ERR_NO_FILE' => [UPLOAD_ERR_NO_FILE],
            'UPLOAD_ERR_NO_TMP_DIR' => [UPLOAD_ERR_NO_TMP_DIR],
            'UPLOAD_ERR_CANT_WRITE' => [UPLOAD_ERR_CANT_WRITE],
            'UPLOAD_ERR_EXTENSION' => [UPLOAD_ERR_EXTENSION],
        ];
    }

    /**
     * @return Generator<int, string>|null
     */
    public static function errorConstantsAndMessagesDataProvider(): ?Generator
    {
        foreach (UploadedFile::ERROR_MESSAGES as $constant => $message) {
            if ($constant === UPLOAD_ERR_OK) {
                continue;
            }

            yield $constant => [$constant, $message];
        }
    }

    /* -------------------------------------------------
     * MOVE TO
     * -------------------------------------------------
     */

    public function testMoveToMoveFileToDesignatedPath(): void
    {
        $stream = new Stream('php://temp', 'wb+');
        $stream->write('foo');
        $uploadedFile = new UploadedFile($stream, 0, UPLOAD_ERR_OK);
        $this->tempFile = $to = (string)tempnam(sys_get_temp_dir(), 'zaphyr');
        $uploadedFile->moveTo($to);

        self::assertFileExists($to);

        $contents = file_get_contents($to);

        self::assertSame($stream->__toString(), $contents);
    }

    public function testMoveToThrowsExceptionWhenAlreadyMoved(): void
    {
        $this->expectException(RuntimeException::class);

        $stream = new Stream('php://temp', 'wb+');
        $stream->write('foo');
        $uploadedFile = new UploadedFile($stream, 0, UPLOAD_ERR_OK);
        $this->tempFile = $to = (string)tempnam(sys_get_temp_dir(), 'zaphyr');
        $uploadedFile->moveTo($to);

        self::assertFileExists($to);

        $uploadedFile->moveTo($to);
    }

    /**
     * @param int $status
     *
     * @dataProvider nonOkErrorStatusDataProvider
     */
    public function testMoveToThrowsExceptionWhenErrorStatusIsPresent(int $status): void
    {
        $this->expectException(RuntimeException::class);

        (new UploadedFile('not ok', 0, $status))
            ->moveTo(__DIR__ . '/' . uniqid('', true));
    }

    public function testMovToCreatesStreamIfOnlyFilenameWasProvided(): void
    {
        $this->tempFile = (string)tempnam(sys_get_temp_dir(), 'zaphyr');
        $uploadedFile = new UploadedFile(
            __FILE__,
            100,
            UPLOAD_ERR_OK,
            basename(__FILE__),
            'text/plain'
        );
        $uploadedFile->moveTo($this->tempFile);
        $original = file_get_contents(__FILE__);
        $test = file_get_contents($this->tempFile);

        self::assertSame($original, $test);
    }

    /**
     * @param int    $constant
     * @param string $message
     *
     * @dataProvider errorConstantsAndMessagesDataProvider
     */
    public function testMoveToThrowsExceptionWhenUploadErrorDetected(
        int $constant,
        string $message
    ): void {
        $this->expectException(RuntimeException::class);

        (new UploadedFile(__FILE__, 100, $constant))->moveTo('/tmp/foo');
    }

    public function testMoveToThrowsExceptionWhenTargetDirectoryDoesNotExists(): void
    {
        $this->expectException(RuntimeException::class);

        (new UploadedFile(__FILE__, 100, UPLOAD_ERR_OK))->moveTo('/nope');
    }

    public function testMoveToThrowsExceptionWhenTargetPathIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new UploadedFile(__FILE__, 100, UPLOAD_ERR_OK))->moveTo('');
    }

    /* -------------------------------------------------
     * GET SIZE
     * -------------------------------------------------
     */

    public function testGetSize(): void
    {
        $size = (new UploadedFile(fopen('php://temp', 'wb+'), 123, UPLOAD_ERR_OK))->getSize();

        self::assertSame(123, $size);
    }

    /* -------------------------------------------------
     * GET ERROR
     * -------------------------------------------------
     */

    /**
     * @param int $status
     *
     * @dataProvider nonOkErrorStatusDataProvider
     */
    public function testGetError(int $status): void
    {
        self::assertSame($status, (new UploadedFile('not ok', 0, $status))->getError());
    }

    /* -------------------------------------------------
     * GET CLIENT FILENAME
     * -------------------------------------------------
     */

    public function testGetClientFileName(): void
    {
        $fileName = (new UploadedFile(
            fopen('php://temp', 'wb+'), 0, UPLOAD_ERR_OK, 'foo.txt')
        )->getClientFilename();

        self::assertSame('foo.txt', $fileName);
    }

    public function testGetClientFileNameCanBeNull(): void
    {
        self::assertNull(
            (new UploadedFile(fopen('php://temp', 'wb+'), 0, UPLOAD_ERR_OK))
                ->getClientFilename()
        );
    }

    /* -------------------------------------------------
     * GET CLIENT MEDIA TYPE
     * -------------------------------------------------
     */

    public function testGetClientMediaType(): void
    {
        $mediaType = (new UploadedFile(
            fopen('php://temp', 'wb+'), 0, UPLOAD_ERR_OK, 'foobar.baz', 'mediatype')
        )->getClientMediaType();

        self::assertSame('mediatype', $mediaType);
    }
}
