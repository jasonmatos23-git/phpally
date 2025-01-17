<?php

namespace CidiLabs\PhpAlly;

use Kaltura\Client\Configuration as KalturaConfiguration;
use Kaltura\Client\Client as KalturaClient;
use Kaltura\Client\Enum\SessionType;
use Kaltura\Client\Type\AssetFilter;
use Kaltura\Client\Type\FilterPager;
use Kaltura\Client\ApiException;
use Kaltura\Client\Plugin\Caption\CaptionPlugin;

class Kaltura {

	const KALTURA_FAIL = 0;
	const KALTURA_FAILED_CONNECTION = 1;
	const KALTURA_SUCCESS = 2;

	private $regex = '';
	private $client;
	private $language;

    public function __construct($language = 'en', $api_key = '', $username = '')
	{
		$this->language = $language;
		$this->api_key = $api_key;
		$this->username = $username;
	}

	/**
	 *	Checks to see if a video is missing caption information in Kaltura
	 *	@param string $link_url The URL to the video or video resource
	 *   @return int 0 if captions are missing, 1 if video is private, 2 if captions exist or not a video
	 */
    function captionsMissing($link_url)
	{
		// If the API key or username is blank, flag the video for manual inspection
		$key_trimmed = trim($this->api_key);
		$username_trimmed = trim($this->username);
		if (empty($key_trimmed) || empty($username_trimmed)) {
			return self::KALTURA_SUCCESS;
		}

		if(($video_id = $this->isKalturaVideo($link_url)) && ($partner_id = $this->getPartnerID($link_url))) {
			$result = $this->getVideoData($video_id, $partner_id);

			if($result->totalCount === 0) {
				return self::KALTURA_FAIL;
			}
		}
		return self::KALTURA_SUCCESS;
	}

    /**
	 *	Checks to see if a video is missing caption information in YouTube
	 *	@param string $link_url The URL to the video or video resource
	 *	@return int 0 if captions are manual and wrong language, 1 if video is private, 2 if there are no captions or if manually generated and correct language
	 */
	function captionsLanguage($link_url)
	{
		// If the API key or username is blank, flag the video for manual inspection
		$key_trimmed = trim($this->api_key);
		$username_trimmed = trim($this->username);
		if (empty($key_trimmed) || empty($username_trimmed)) {
			return self::KALTURA_SUCCESS;
		}

		if(($video_id = $this->isKalturaVideo($link_url)) && ($partner_id = $this->getPartnerID($link_url))) {
			$result = $this->getVideoData($video_id, $partner_id);
			$captionsArray = $result->objects;
			foreach($captionsArray as $caption) 
			{
				if(substr($caption->languageCode, 0, 2) === substr($this->language, 0, 2)) {
					return self::KALTURA_SUCCESS;
				} 
			}
			return self::KALTURA_FAIL;
		}

		return self::KALTURA_SUCCESS;
	}

	/**
	 *	Checks to see if the provided link URL is a Kaltura video. If so, it returns
	 *	the video code, if not, it returns null.
	 *	@param string $link_url The URL to the video or video resource
	 *	@return mixed FALSE if it's not a Kaltura video, or a string video ID if it is
	 */
	private function isKalturaVideo($link_url)
	{
		$regex = '@\bkaltura\b.*\bentry_id=(.{10})@';
		$matches = null;

		if (preg_match($regex, trim($link_url), $matches)) {
			return trim($matches[1]);
		}
		return false;
	}

	private function getPartnerID($link_url)
	{
		$regex = '@partner_id/(.{7})@';
		$matches = null;

		if (preg_match($regex, trim($link_url), $matches)) {
			return trim($matches[1]);
		}
		return false;
	}

	/**
	 *	Makes the api call to get the caption data for the video.
	 *	@param string $link_url The URL to the video or video resource
	 *	@return mixed FALSE if the api calls fails or its not a Kaltura video,
	 *  or an array of caption objects if it is
	 */
	function getVideoData($video_id, $partner_id)
	{
		$config = new KalturaConfiguration($partner_id);
		$config->setServiceUrl('https://www.kaltura.com');
		$this->client = new KalturaClient($config);
		$ks = $this->client->generateSession(
			$this->api_key,
			$this->username,
			SessionType::ADMIN,
			$partner_id);
			$this->client->setKS($ks);

		$captionPlugin = CaptionPlugin::get($this->client);
		$filter = new AssetFilter();
		$filter->entryIdIn = $video_id;
		$pager = new FilterPager();

		$result = $captionPlugin->captionAsset->listAction($filter, $pager);

		return $result;
	}

}