<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessage;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Exceptions\RuntimeException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * @const array<int, string>
     */
    public const ERROR_MESSAGES = [
        UPLOAD_ERR_OK => 'There is no error, the file uploaded with success',
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
    ];

    /**
     * @var string
     */
    private string $file = '';

    /**
     * @var bool
     */
    private bool $moved = false;

    /**
     * @var StreamInterface|null
     */
    private StreamInterface|null $stream = null;

    /**
     * @param resource|string|StreamInterface $streamOrFile
     * @param int|null                        $size
     * @param int                             $error
     * @param string|null                     $clientFilename
     * @param string|null                     $clientMediaType
     */
    public function __construct(
        $streamOrFile,
        private readonly int|null $size,
        private readonly int $error,
        private readonly string|null $clientFilename = null,
        private readonly string|null $clientMediaType = null
    ) {
        if ($error === UPLOAD_ERR_OK) {
            if (is_string($streamOrFile)) {
                $this->file = $streamOrFile;
            }

            if (is_resource($streamOrFile)) {
                $this->stream = new Stream($streamOrFile);
            }

            if (!$this->file && !$this->stream) {
                if (!$streamOrFile instanceof StreamInterface) {
                    throw new InvalidArgumentException('Invalid stream or file provided');
                }

                $this->stream = $streamOrFile;
            }
        }

        if (0 > $error || 8 < $error) {
            throw new InvalidArgumentException(
                'Invalid error status. Must be an UPLOAD_ERR_* constant'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStream(): StreamInterface
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::ERROR_MESSAGES[$this->error]);
        }

        if ($this->moved) {
            throw new RuntimeException('Cannot retrieve stream after it has already moved');
        }

        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        $this->stream = new Stream($this->file);

        return $this->stream;
    }

    /**
     * {@inheritdoc}
     */
    public function moveTo(string $targetPath): void
    {
        if ($this->moved) {
            throw new RuntimeException('Cannot move file. Already moved');
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::ERROR_MESSAGES[$this->error]);
        }

        if ($targetPath === '') {
            throw new InvalidArgumentException(
                'Invalid path provided for move operation. Must be a non-empty string'
            );
        }

        $targetDirectory = dirname($targetPath);

        if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
            throw new RuntimeException(
                'The target directory "' . $targetDirectory . '" does not exists or is not writable'
            );
        }

        if (!$this->file || PHP_SAPI === 'cli') {
            $handle = fopen($targetPath, 'wb+');

            if ($handle === false) {
                throw new RuntimeException('Unable to write to path "' . $targetPath . '"');
            }

            $stream = $this->getStream();
            $stream->rewind();

            while (!$stream->eof()) {
                fwrite($handle, $stream->read(4096));
            }

            fclose($handle);
        } elseif (move_uploaded_file($this->file, $targetPath) === false) {
            throw new RuntimeException('An error occurred while moving uploaded file');
        }

        $this->moved = true;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
