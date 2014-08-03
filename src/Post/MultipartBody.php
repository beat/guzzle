<?php

namespace GuzzleHttp\Post;

use GuzzleHttp\Stream;
use GuzzleHttp\Stream\MetadataStreamInterface;
use GuzzleHttp\Stream\StreamInterface;

/**
 * Stream that when read returns bytes for a streaming multipart/form-data body
 */
class MultipartBody implements Stream\StreamInterface
{
    //BB use Stream\StreamDecoratorTrait;

	/** @var StreamInterface Decorated stream */
	private $stream;

	/*
	 * @param StreamInterface $stream Stream to decorate
	 *
	public function __construct(StreamInterface $stream)
	{
		$this->stream = $stream;
	}
	*/

	public function __toString()
	{
		try {
			$this->seek(0);
			return $this->getContents();
		} catch (\Exception $e) {
			// Really, PHP? https://bugs.php.net/bug.php?id=53648
			trigger_error('StreamDecorator::__toString exception: '
				. (string) $e, E_USER_ERROR);
			return '';
		}
	}

	public function getContents($maxLength = -1)
	{
		return \GuzzleHttp\Stream\copy_to_string($this, $maxLength);
	}

	/**
	 * Allow decorators to implement custom methods
	 *
	 * @param string $method Missing method name
	 * @param array  $args   Method arguments
	 *
	 * @return mixed
	 */
	public function __call($method, array $args)
	{
		$result = call_user_func_array(array($this->stream, $method), $args);

		// Always return the wrapped object if the result is a return $this
		return $result === $this->stream ? $this : $result;
	}

	public function close()
	{
		return $this->stream->close();
	}

	public function getMetadata($key = null)
	{
		return $this->stream instanceof MetadataStreamInterface
			? $this->stream->getMetadata($key)
			: null;
	}

	public function detach()
	{
		$this->stream->detach();

		return $this;
	}

	public function getSize()
	{
		return $this->stream->getSize();
	}

	public function eof()
	{
		return $this->stream->eof();
	}

	public function tell()
	{
		return $this->stream->tell();
	}

	public function isReadable()
	{
		return $this->stream->isReadable();
	}

	/*BB
	public function isWritable()
	{
		return $this->stream->isWritable();
	}
	*/

	public function isSeekable()
	{
		return $this->stream->isSeekable();
	}

	public function seek($offset, $whence = SEEK_SET)
	{
		return $this->stream->seek($offset, $whence);
	}

	public function read($length)
	{
		return $this->stream->read($length);
	}

	public function write($string)
	{
		return $this->stream->write($string);
	}

	//BB end of use StreamDecoratorTrait

    private $boundary;

    /**
     * @param array  $fields   Associative array of field names to values where
     *                         each value is a string.
     * @param array  $files    Associative array of PostFileInterface objects
     * @param string $boundary You can optionally provide a specific boundary
     * @throws \InvalidArgumentException
     */
    public function __construct(
        array $fields = array(),
        array $files = array(),
        $boundary = null
    ) {
        $this->boundary = $boundary ?: uniqid();
        $this->stream = $this->createStream($fields, $files);
    }

    /**
     * Get the boundary
     *
     * @return string
     */
    public function getBoundary()
    {
        return $this->boundary;
    }

    public function isWritable()
    {
        return false;
    }

    /**
     * Get the string needed to transfer a POST field
     */
    private function getFieldString($name, $value)
    {
        return sprintf(
            "--%s\r\nContent-Disposition: form-data; name=\"%s\"\r\n\r\n%s\r\n",
            $this->boundary,
            $name,
            $value
        );
    }

    /**
     * Get the headers needed before transferring the content of a POST file
     */
    private function getFileHeaders(PostFileInterface $file)
    {
        $headers = '';
        foreach ($file->getHeaders() as $key => $value) {
            $headers .= "{$key}: {$value}\r\n";
        }

        return "--{$this->boundary}\r\n" . trim($headers) . "\r\n\r\n";
    }

    /**
     * Create the aggregate stream that will be used to upload the POST data
     */
    private function createStream(array $fields, array $files)
    {
        $stream = new Stream\AppendStream();

        foreach ($fields as $name => $field) {
            $stream->addStream(
                Stream\create($this->getFieldString($name, $field))
            );
        }

        foreach ($files as $file) {

            if (!$file instanceof PostFileInterface) {
                throw new \InvalidArgumentException('All POST fields must '
                    . 'implement PostFieldInterface');
            }

            $stream->addStream(
                Stream\create($this->getFileHeaders($file))
            );
            $stream->addStream($file->getContent());
            $stream->addStream(Stream\create("\r\n"));
        }

        // Add the trailing boundary
        $stream->addStream(Stream\create("--{$this->boundary}--"));

        return $stream;
    }
}
