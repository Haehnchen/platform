<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\Exception\MissingFileExtensionException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Symfony\Component\HttpFoundation\Request;

class FileFetcher
{
    public function fetchRequestData(Request $request, string $fileName): MediaFile
    {
        $extension = $this->getExtensionFromRequest($request);
        $expectedLength = (int) $request->headers->get('content-length');

        $inputStream = $request->getContent(true);
        $destStream = $this->openDestinationStream($fileName);

        try {
            $bytesWritten = $this->copyStreams($inputStream, $destStream);
        } finally {
            fclose($inputStream);
            fclose($destStream);
        }

        if ($expectedLength !== $bytesWritten) {
            throw new UploadException('expected content-length did not match actual size');
        }

        return new MediaFile(
            $fileName,
            mime_content_type($fileName),
            $extension,
            $bytesWritten
        );
    }

    public function fetchFileFromURL(Request $request, string $fileName): MediaFile
    {
        $url = $this->getUrlFromRequest($request);
        $extension = $this->getExtensionFromRequest($request);

        $inputStream = $this->openSourceFromUrl($url);
        $destStream = $this->openDestinationStream($fileName);

        try {
            $writtenBytes = $this->copyStreams($inputStream, $destStream);
        } finally {
            fclose($inputStream);
            fclose($destStream);
        }

        return new MediaFile(
            $fileName,
            mime_content_type($fileName),
            $extension,
            $writtenBytes
        );
    }

    /**
     * @throws MissingFileExtensionException
     */
    private function getExtensionFromRequest(Request $request): string
    {
        $extension = $request->query->get('extension');
        if ($extension === null) {
            throw new MissingFileExtensionException();
        }

        return $extension;
    }

    /**
     * @throws UploadException
     */
    private function getUrlFromRequest(Request $request): string
    {
        $url = $request->request->get('url');

        if ($url === null) {
            throw new UploadException('You must provide a valid url.');
        }

        if (!$this->isUrlValid($url)) {
            throw new UploadException('malformed url: ' . $url);
        }

        return $url;
    }

    /**
     * @throws UploadException
     *
     * @return resource
     */
    private function openSourceFromUrl(string $url)
    {
        $inputStream = @fopen($url, 'r');

        if ($inputStream === false) {
            throw new UploadException("Could open source stream from {$url}");
        }

        return $inputStream;
    }

    /**
     * @throws UploadException
     *
     * @return resource
     */
    private function openDestinationStream(string $filename)
    {
        $inputStream = @fopen($filename, 'w');

        if ($inputStream === false) {
            throw new UploadException("Could not open Stream to write upload data: ${filename}");
        }

        return $inputStream;
    }

    private function copyStreams($sourceStream, $destStream): int
    {
        $writtenBytes = stream_copy_to_stream($sourceStream, $destStream);

        if ($writtenBytes === false) {
            throw new UploadException('Error while copying media from source');
        }

        return $writtenBytes;
    }

    private function isUrlValid(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:/', $url);
    }
}
