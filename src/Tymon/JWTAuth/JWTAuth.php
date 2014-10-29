<?php

namespace Tymon\JWTAuth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Auth\AuthInterface;
use Tymon\JWTAuth\Exceptions\JWTAuthException;
use Tymon\JWTAuth\JWT\JWTInterface;

class JWTAuth
{

    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $user;

    /**
     * @var \Tymon\JWTAuth\JWT\JWTInterface
     */
    protected $provider;

    /**
     * @var \Tymon\JWTAuth\Auth\AuthInterface
     */
    protected $auth;

    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * @var string
     */
    protected $identifier = 'id';

    /**
     * @var string
     */
    protected $token;

    /**
     * @param \Illuminate\Database\Eloquent\Model  $user
     * @param \Tymon\JWTAuth\JWT\JWTInterface      $provider
     * @param \Tymon\JWTAuth\Auth\AuthInterface    $auth
     * @param \Illuminate\Http\Request             $request
     */
    public function __construct(Model $user, JWTInterface $provider, AuthInterface $auth, Request $request)
    {
        $this->user = $user;
        $this->provider = $provider;
        $this->auth = $auth;
        $this->request = $request;
    }

    /**
     * Find a user using the user identifier in the subject claim
     *
     * @param  string  $token
     * @return mixed
     */
    public function toUser($token = false)
    {
        $this->requireToken($token);

        $this->provider->decode($this->token);

        if (! $user = $this->user->where($this->identifier, $this->provider->getSubject())->first()) {
            return false;
        }

        return $user;
    }

    /**
     * Generate a token using the user identifier as the subject claim
     *
     * @param  $user
     * @return string
     */
    public function fromUser($user)
    {
        return $this->provider->encode($user->{$this->identifier})->get();
    }

    /**
     * Attempt to authenticate the user and return the token
     *
     * @param  array  $credentials
     * @return false|string
     */
    public function attempt(array $credentials = [])
    {
        if (! $this->auth->check($credentials)) {
            return false;
        }

        return $this->fromUser($this->auth->user());
    }

    /**
     * Log the user in via the token
     *
     * @param  mixed  $token
     * @return boolean
     */
    public function login($token = false)
    {
        $this->requireToken($token);

        $id = $this->provider->getSubject($this->token);

        if (! $user = $this->auth->checkUsingId($id)) {
            return false;
        }

        return $user;
    }

    /**
     * Get the token from the request
     *
     * @param  string  $query
     * @return false|string
     */
    public function getToken($query = 'token')
    {
        if (! $token = $this->parseAuthHeader()) {
            if (! $token = $this->request->query($query, false)) {
                return false;
            }
        }

        $this->setToken($token);

        return $token;
    }

    /**
     * Parse token from the authorization header
     *
     * @return false|string
     */
    protected function parseAuthHeader($method = 'bearer')
    {
        $header = $this->request->headers->get('authorization');

        if (! starts_with(strtolower($header), $method)) {
            return false;
        }

        return trim(str_ireplace($method, '', $header));
    }

    /**
     * Check whether the token is valid
     *
     * @param  mixed  $token
     * @return bool
     */
    public function isValid($token = false)
    {
        $this->requireToken($token);

        try {
            $this->provider->decode($this->token);
        } catch (JWTException $e) {
            return false;
        }

        return true;
    }

    /**
     * Get the JWT provider
     *
     * @return Providable
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Set the identifier
     *
     * @param string $identifier
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Set the token
     *
     * @param string  $token
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Ensure that a token is available
     *
     * @param  mixed  $token
     * @return void
     */
    protected function requireToken($token)
    {
        if ($token) {
            $this->setToken($token);
        } else {
            if (! $this->token) {
                throw new JWTAuthException('A token is required');
            }
        }
    }

    /**
     * Magically call the JWT provider
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->provider, $method)) {
            return call_user_func_array([$this->provider, $method], $parameters);
        }

        throw new \BadMethodCallException('Method [$method] does not exist.');
    }
}
