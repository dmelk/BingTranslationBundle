<?php

namespace Melk\BingTranslationBundle\Service;
use Melk\BingTranslationBundle\MelkBingTranslationBundle;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @author    Michael Potienko <potienko.m@gmail.com>
 * @copyright 2015 Modera Foundation
 */
class Translator
{

    /**
     * MS application client id
     * @var string
     */
    private $clientId;

    /**
     * MS application client secret
     * @var string
     */
    private $clientSecret;

    /**
     * Path to the yml config file
     * @var string
     */
    private $configPath;

    /**
     * @param KernelInterface $kernel
     * @param $clientId
     * @param $clientSecret
     */
    public function __construct(KernelInterface $kernel, $clientId, $clientSecret)
    {
        $this->configPath = $kernel->getRootDir().'/../app/Resources/';
        if (!file_exists($this->configPath)) mkdir($this->configPath);
        $this->configPath .= MelkBingTranslationBundle::BUNDLE_NAME.'/';
        if (!file_exists($this->configPath)) mkdir($this->configPath);
        $this->configPath .= 'config.yml';

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * Get info from config file if it's exists
     * @return bool|mixed
     */
    private function extractConfig()
    {
        if (!file_exists($this->configPath)) {
            return false;
        }

        $parser = new Parser();
        try {
            $yaml = file_get_contents($this->configPath);
            return $parser->parse($yaml, true);
        } catch (ParseException $e) {
            return false;
        }
    }

    /**
     * Saves the config to the file
     * @param $config
     */
    private function saveConfig($config)
    {
        $dumper = new Dumper();
        $yaml = $dumper->dump($config);
        file_put_contents($this->configPath, $yaml);
    }

    /**
     * Retrieves access token. If it's expired then it will be renewed.
     *
     * @return mixed
     */
    private function getAccessToken()
    {
        $config = $this->extractConfig();

        $now = new \DateTime();
        $now = $now->getTimestamp();

        if ($config !== false) {
            if ($config['expires'] < $now) return $config['access_token'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://datamarket.accesscontrol.windows.net/v2/OAuth2-13');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            http_build_query(
                array(
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope'         => 'http://api.microsofttranslator.com'
                )
            )
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawOutput = curl_exec($ch);
        curl_close($ch);

        $serverOutput = json_decode($rawOutput);
        if (!is_object($serverOutput) || !isset($serverOutput->access_token)) {
            throw new \RuntimeException('Authentication failed: '.$rawOutput);
        }

        $accessToken = $serverOutput->access_token;
        $this->saveConfig([
            'access_token' => $accessToken,
            'expires' => $now + $serverOutput->expires_in
        ]);

        return $accessToken;
    }

    /**
     * @param $text
     * @return string|null
     */
    public function detectLanguage($text)
    {
        $accessToken = $this->getAccessToken();

        $ch = curl_init();
        $query = http_build_query(
            array(
                'text' => $text
            )
        );
        curl_setopt($ch, CURLOPT_URL, 'http://api.microsofttranslator.com/V2/Http.svc/Detect?'.$query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization:bearer '.$accessToken
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $serverOutput = curl_exec($ch);
        curl_close ($ch);

        preg_match_all('/<string (.*?)>(.*?)<\/string>/s', $serverOutput, $matches);

        return (isset($matches[2]) && is_array($matches[2]))? $matches[2][0] : null;
    }

    /**
     * @param $text
     * @param $from
     * @param $to
     * @return string|null
     */
    public function translate($text, $from, $to)
    {
        $accessToken = $this->getAccessToken();

        $ch = curl_init();
        $query = http_build_query(
            array(
                'text' => $text,
                'from' => $from,
                'to'   => $to
            )
        );
        curl_setopt($ch, CURLOPT_URL, 'http://api.microsofttranslator.com/V2/Http.svc/Translate?'.$query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization:bearer '.$accessToken
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $serverOutput = curl_exec($ch);
        curl_close ($ch);

        preg_match_all('/<string (.*?)>(.*?)<\/string>/s', $serverOutput, $matches);

        return (isset($matches[2]) && is_array($matches[2]))? $matches[2][0] : null;
    }

}