<?php

namespace App\AuthProviders\overrides;

class ErugoOpenIDConnectclient extends \Jumbojett\OpenIDConnectClient
{
    
    //override the getStateValue method to return the state value
    //this overcomes the limitation of the OpenIDConnectClient class
    //which does not allow us to set OR get the state value
    //thus preventing us from maintaining the state across redirects
    
    public function getStateValue()
    {
        \Log::info("Getting state value");
        return $this->getSessionKey('openid_connect_state');
    }

    public function setState(string $state): string
    {
        $this->setSessionKey('openid_connect_state', $state);
        return $state;
    }

    protected function commitSession() {
        $this->startSession();
    }

    /**
     * Public wrapper for getProviderConfigValue
     * Exposes the protected method for use in OIDCAuthProvider
     */
    public function getProviderConfigValuePublic(string $param, ?string $default = null)
    {
        return $this->getProviderConfigValue($param, $default);
    }

    /**
     * Public wrapper for fetchURL
     * Exposes the protected method for use in OIDCAuthProvider
     */
    public function fetchURLPublic(string $url, ?array $post_body = null, array $headers = [])
    {
        return $this->fetchURL($url, $post_body, $headers);
    }
}
