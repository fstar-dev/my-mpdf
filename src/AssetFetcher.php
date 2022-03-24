<?php

namespace Mpdf;

use Mpdf\Exception\AssetFetchingException;
use Mpdf\File\StreamWrapperChecker;
use Mpdf\Http\ClientInterface;
use Mpdf\Http\Request;
use Mpdf\Log\Context as LogContext;
use Psr\Log\LoggerInterface;

class AssetFetcher implements \Psr\Log\LoggerAwareInterface
{

	private $mpdf;

	private $http;

	private $logger;

	public function __construct(Mpdf $mpdf, ClientInterface $http, LoggerInterface $logger)
	{
		$this->mpdf = $mpdf;
		$this->http = $http;
		$this->logger = $logger;
	}

	public function fetchDataFromPath($path, $originalSrc = null)
	{
		/**
		 * Prevents insecure PHP object injection through phar:// wrapper
		 * @see https://github.com/mpdf/mpdf/issues/949
		 * @see https://github.com/mpdf/mpdf/issues/1381
		 */
		$wrapperChecker = new StreamWrapperChecker($this->mpdf);

		if ($wrapperChecker->hasBlacklistedStreamWrapper($path)) {
			throw new AssetFetchingException('File contains an invalid stream. Only ' . implode(', ', $wrapperChecker->getWhitelistedStreamWrappers()) . ' streams are allowed.');
		}

		if ($originalSrc && $wrapperChecker->hasBlacklistedStreamWrapper($originalSrc)) {
			throw new AssetFetchingException('File contains an invalid stream. Only ' . implode(', ', $wrapperChecker->getWhitelistedStreamWrappers()) . ' streams are allowed.');
		}

		return $this->isPathLocal($path)
			? $this->fetchLocalContent($path, $originalSrc)
			: $this->fetchRemoteContent($path);
	}

	public function fetchLocalContent($path, $originalSrc)
	{
		$data = '';

		if ($originalSrc && $this->mpdf->basepathIsLocal && $check = @fopen($originalSrc, 'rb')) {
			fclose($check);
			$path = $originalSrc;
			$this->logger->debug(sprintf('Fetching (file_get_contents) content of file "%s" with local basepath', $path), ['context' => LogContext::REMOTE_CONTENT]);
			$data = file_get_contents($path);
		}

		if ($path && !$data && $check = @fopen($path, 'rb')) {
			fclose($check);
			$this->logger->debug(sprintf('Fetching (file_get_contents) content of file "%s" with non-local basepath', $path), ['context' => LogContext::REMOTE_CONTENT]);
			$data = file_get_contents($path);
		}

		return $data;
	}

	public function fetchRemoteContent($path)
	{
		$data = '';

		try {

			$this->logger->debug(sprintf('Fetching remote content of file "%s"', $path), ['context' => LogContext::REMOTE_CONTENT]);

			/** @var \Mpdf\Http\Response $response */
			$response = $this->http->sendRequest(new Request('GET', $path));

			if ($response->getStatusCode() !== 200) {

				$message = sprintf('Non-OK HTTP response "%s" on fetching remote content "%s" because of an error', $response->getStatusCode(), $path);
				if ($this->mpdf->debug) {
					throw new \Mpdf\MpdfException($message);
				}

				$this->logger->info($message);

				return $data;
			}

			$data = $response->getBody()->getContents();

		} catch (\InvalidArgumentException $e) {
			$message = sprintf('Unable to fetch remote content "%s" because of an error "%s"', $path, $e->getMessage());
			if ($this->mpdf->debug) {
				throw new \Mpdf\MpdfException($message, 0, $e);
			}

			$this->logger->warning($message);
		}

		return $data;
	}

	public function isPathLocal($path)
	{
		return strpos($path, '://') === false; // @todo More robust implementation
	}

	public function setLogger(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

}
