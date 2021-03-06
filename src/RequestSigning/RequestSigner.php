<?php

namespace ApiClients\Tools\Psr7\Oauth1\RequestSigning;

use ApiClients\Tools\Psr7\Oauth1\Definition\AccessToken;
use ApiClients\Tools\Psr7\Oauth1\Definition\ConsumerKey;
use ApiClients\Tools\Psr7\Oauth1\Definition\ConsumerSecret;
use ApiClients\Tools\Psr7\Oauth1\Definition\TokenSecret;
use ApiClients\Tools\Psr7\Oauth1\Signature\HmacSha1Signature;
use ApiClients\Tools\Psr7\Oauth1\Signature\Signature;
use Psr\Http\Message\RequestInterface;

class RequestSigner
{
    /**
     * @var ConsumerKey
     */
    private $consumerKey;

    /**
     * @var ConsumerSecret
     */
    private $consumerSecret;

    /**
     * @var AccessToken
     */
    private $accessToken;

    /**
     * @var TokenSecret
     */
    private $tokenSecret;

    /**
     * @var Signature
     */
    private $signature;

    /**
     * @param ConsumerKey $consumerKey
     * @param ConsumerSecret $consumerSecret
     * @param Signature $signature
     */
    public function __construct(ConsumerKey $consumerKey, ConsumerSecret $consumerSecret, Signature $signature = null)
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->signature = $signature ?: new HmacSha1Signature($consumerSecret);
    }

    /**
     * @param AccessToken $accessToken
     * @param TokenSecret $tokenSecret
     * @return static
     */
    public function withAccessToken(AccessToken $accessToken, TokenSecret $tokenSecret)
    {
        $clone = clone $this;
        $clone->accessToken = $accessToken;
        $clone->tokenSecret = $tokenSecret;

        $clone->signature = $clone->signature
            ->withTokenSecret($tokenSecret);

        return $clone;
    }

    /**
     * @return static
     */
    public function withoutAccessToken()
    {
        $clone = clone $this;
        $clone->accessToken = null;
        $clone->tokenSecret = null;

        $clone->signature = $clone->signature
            ->withoutTokenSecret();

        return $clone;
    }

    /**
     * @param RequestInterface $request
     * @return RequestInterface
     */
    public function sign(RequestInterface $request)
    {
        $parameters = [
            'oauth_consumer_key' => (string) $this->consumerKey,
            'oauth_nonce' => $this->generateNonce(),
            'oauth_signature_method' => $this->signature->getMethod(),
            'oauth_timestamp' => $this->generateTimestamp(),
            'oauth_version' => '1.0',
        ];

        if ($this->accessToken) {
            $parameters['oauth_token'] = (string) $this->accessToken;
        }

        $parameters = $this->mergeSignatureParameter($request, $parameters);

        return $request->withHeader('Authorization', $this->generateAuthorizationheader($parameters));
    }

    public function signToRequestAuthorization(
        RequestInterface $request,
        $callbackUri,
        array $additionalParameters = []
    ) {
        $parameters = [
            'oauth_consumer_key' => (string) $this->consumerKey,
            'oauth_nonce' => $this->generateNonce(),
            'oauth_signature_method' => $this->signature->getMethod(),
            'oauth_timestamp' => $this->generateTimestamp(),
            'oauth_version' => '1.0',
            'oauth_callback' => $callbackUri,
        ];

        $parameters = array_merge($parameters, $additionalParameters);

        $parameters = $this->mergeSignatureParameter($request, $parameters);

        return $request->withHeader('Authorization', $this->generateAuthorizationheader($parameters));
    }

    /**
     * @param RequestInterface $request
     * @param array $parameters
     * @return array
     */
    private function mergeSignatureParameter(RequestInterface $request, array $parameters)
    {
        $body = [];
        if ($request->getMethod() === 'POST' &&
            $request->getHeaderLine('Content-Type') === 'application/x-www-form-urlencoded') {
            parse_str((string) $request->getBody(), $body);
        }

        $signature = $this->signature->sign($request->getUri(), array_merge($body, $parameters), $request->getMethod());

        $parameters['oauth_signature'] = $signature;

        return $parameters;
    }

    private function generateTimestamp()
    {
        $dateTime = new \DateTimeImmutable();

        return $dateTime->format('U');
    }

    private function generateNonce($length = 32)
    {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
    }

    private function generateAuthorizationheader(array $parameters)
    {
        array_walk($parameters, function (&$value, $key) {
            $value = rawurlencode($key).'="'.rawurlencode($value).'"';
        });

        return 'OAuth '.implode(',', $parameters);
    }
}
