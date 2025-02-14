<?php
/**
 * Session de l'utilisateur pour la connexion au cloud Tuya
 *
 * @author Olivier <sabinus52@gmail.com>
 *
 * @package TuyaCloudApi
 */

namespace Sabinus\TuyaCloudApi\Session;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;


class Session
{

    /**
     * Utilisateur de connection
     * 
     * @var String
     */
    private $username;

    /**
     * Mot de passe de connection
     * 
     * @var String
     */
    private $password;

    /**
     * Code du pays de l'utilisateur = indicatif téléphonique (33 : France)
     * 
     * @var Integer
     */
    private $countryCode;

    /**
     * Objet de la plateform Tuya
     * 
     * @var Platform
     */
    private $platform;

    /**
     * Client HTTP
     * 
     * @var Client
     */
    private $client;

    /**
     * Jeton de connexion
     * 
     * @var Token
     */
    private $token;


    /**
     * Constructeur
     * 
     * @param String  $username : Nom d'utilisateur
     * @param String  $password : Mot de passe
     * @param Integer $country  : Code du pays
     * @param String  $biztype  : Type de la plateforme
     */
    public function __construct($username, $password, $country, $biztype = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->countryCode = $country;
        $this->platform = new Platform($biztype);
        $this->token = new Token();
        $this->client = $this->_createClient();
    }


    /**
     * Retourne la valeur du jeton
     * 
     * @return String
     */
    public function getToken()
    {
        if ( !$this->token->has() ) {
            $this->_createToken();
        }
        if ( !$this->token->isValid() ) {
            $this->_refreshToken();
        }
        return $this->token->get();
    }


    /**
     * Retourne l'objet du client HTTP
     * 
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }


    /**
     * Créer l'objet du client HTTP en fonction de la région de la plateforme à utiliser
     * 
     * @return Client
     */
    private function _createClient()
    {
        return new Client(array(
            'base_uri' => $this->platform->getBaseUrl(),
            'connect_timeout' => 2.0,
            'timeout' => 2.0,
        ));
    }


    /**
     * Créer le jeton de connexion
     */
    private function _createToken()
    {
        $response = $this->client->post(new Uri('homeassistant/auth.do'), array(
            'form_params' => array(
                'userName'    => $this->username,
                'password'    => $this->password,
                'countryCode' => $this->countryCode,
                'bizType'     => $this->platform->getBizType(),
                'from'        => 'tuya',
            ),
        ));
        //print 'CREATE : '.$response->getBody()."\n";
        $response = json_decode((string) $response->getBody(), true);
        $this->checkResponse($response, 'Failed to get a token');

        // Affecte le résultat dans le token
        $this->token->set($response);

        // La valeur du token retoune la region pour indiquer sur quelle plateforme, on doit se connecter
        $this->platform->setRegionFromToken($this->token->get());
        // Recréer l'objet du client HTTP pour la nouvelle base URL en fonction de la region
        $this->client = $this->_createClient();
    }


    /**
     * Rafraichit le jeton
     */
    private function _refreshToken()
    {
        $response = $this->client->get(new Uri('homeassistant/access.do'), array(
            'query' => array(
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->token->getTokenRefresh(),
            ),
        ));
        //print 'REFRESH : '.$response->getBody()."\n";
        $response = json_decode((string) $response->getBody(), true);
        $this->checkResponse($response, 'Failed to refresh token');

        // Affecte le résultat dans le token
        $this->token->set($response);
    }


    /**
     * Vérifie si pas d'erreur dans le retour de la requête
     * 
     * @param Array $response : Réponse de la requete http
     * @param String $message : Message par défaut
     * @throws Exception
     */
    public function checkResponse(array $response, $message = null)
    {
        if ( isset($response['responseStatus']) && $response['responseStatus'] === 'error' ) {
            $message = isset($response['errorMsg']) ? $response['errorMsg'] : $message;
            throw new \Exception($message);
        }
    }

}