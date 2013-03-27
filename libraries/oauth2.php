<?php
namespace SebRenauld;
use Exception;
use Laravel\Event as Event;
use Laravel\Response as Response;
use Laravel\Config as Config;
use Laravel\Validator as Validator;
use stdClass;

class OAuth2 {
	public static function verify($token) {
		$t = OAuth2\Models\Token::where("type","=",1)->where("code","=",$token)->first();
		if (!$t) return false;
		if ($t->hasExpired()) return false;
		$vr = OAuth2\Models\Client::where("id","=",$t->client_id)->first();
		if (!$vr) return false;
		return $vr;
	}
	protected function getClientCredentials() {
		// This function has been nicked straight off the OAuth code samples on oauth.net
		//
		// ...Not that it does anything important, but you know.
		
		if (isset($_SERVER["PHP_AUTH_USER"]) && $_POST && isset($_POST["client_id"]))
			return false;
		if (isset($_SERVER["PHP_AUTH_USER"]))
			return array("username",$_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"]);
		if ($_REQUEST && isset($_REQUEST["client_id"])) {
			if (isset($_REQUEST["client_secret"]))
				return array("id",$_REQUEST["client_id"], $_REQUEST["client_secret"]);
			return array("id",$_REQUEST["client_id"], NULL);
		}
	}
	public function grantAccessToken($params=array()) {
		if (empty($params['grant_type'])) {
			return Event::until("oauth2.error.json",array("","400:invalid_request"));
		}
		if (!isset($params['scope'])) $params['scope'] = "";
		/* if (!in_array($params['scope'], OAuth2\Models\Scope::listScopes())) {
			return Event::until("oauth2.error.json",array("","400:invalid_request"));
		} */
		// Find the user
		$userData = $this->getClientCredentials();
		if (!$userData) {
			return Event::until("oauth2.error.json",array("","400:invalid_client"));
		}
		
		// Poll the user auth system to see if the client is there
		$client = Event::until("oauth2.login.".$userData[0],array($userData[1],$userData[2]));
		if (empty($client) || !($client instanceof OAuth2\Models\Client)) {
			if (!empty($client) && ($client instanceof Response)) return $client;
			else return Event::until("oauth2.error.json",array("","400:invalid_client"));
		}
		
		$requestedScopes = array();
		$scopes = explode(" ",$params['scope']);
		foreach ($scopes as $v) {
			$scopeName = urldecode($v);
			if (!empty($scopeName)) {
				$s = OAuth2\Models\Scope::where("name","=",$scopeName)->first();
				if (!$s) {
					return Event::until("oauth2.error.json",array("","400:invalid_scope"));
				}
				$requestedScopes[] = $s;
			}
		}
		$clientScopes = $client->scopes()->get();
		foreach ($requestedScopes as $v) {
			if (!empty($v)) {
				foreach ($clientScopes as $clientScope) {
					if ($v->id == $clientScope->id) {
						continue 2;
					}
				}
				return Event::until("oauth2.error.json",array("","400:invalid_scope"));
			}
		}
		// We have a valid client and request. Do the granting
		switch ($params['grant_type']) {
			case "authorization_code":
				if (empty($params['code']) || empty($params['redirect_uri'])) {
					return Event::until("oauth2.error.json",array("","400:invalid_request"));
				}
				$token = OAuth2\Models\Token::where("code","=",$params['code'])->where("type","=",3)->where("client_id","=",$client->id)->first();
				if (!$token) {
					return Event::until("oauth2.error.json",array("","400:invalid_grant"));
				}
				if (empty($params['redirect_uri'])) {
					$params['redirect_uri'] = $client->redirect;
				}
				if (substr($params['redirect_uri'],0,strlen($client->redirect)) !== $client->redirect) {
					return Event::until("oauth2.error.json",array("","400:invalid_request"));
				}
				if ($token->hasExpired()) {
					return Event::until("oauth2.error.json",array("","400:expired_token"));
				}
				switch (Config::get("oauth2-server::oauth2.mode","flexible")) {
					case "strict":
						$tokenScopes = 0;
						$totalScopes = 0;
						foreach ($token->scopes()->get() as $v) {
							$totalScopes++;
							foreach ($requestedScopes as $vR) {
								if ($v->id == $vR->id) {
									$tokenScopes++;
									continue 2;
								}
							}
							// DAFUQ! Someone tried to cheat with the tokens!
							return Event::until("oauth2.error.json",array("","400:invalid_request"));
						}
						if ($tokenScopes != $totalScopes) return Event::until("oauth2.error.json",array("","400:invalid_request"));
						break;
					case "dynamic":
						$tokenScopes = 0;
						$totalScopes = 0;
						foreach ($token->scopes()->get() as $v) {
							$totalScopes++;
							foreach ($requestedScopes as $vR) {
								if ($v->id == $vR->id) {
									$tokenScopes++;
									continue 2;
								}
							}
							// DAFUQ! Someone tried to cheat with the tokens!
							return Event::until("oauth2.error.json",array("","400:invalid_request"));
						}
						break;
					default:
						// The scope the person gave is basically irrelevant
						$requestedScopes = array();
						foreach ($token->scopes()->get() as $v) {
							$requestedScopes[] = $v;
						}
				}
				$token->delete();
				break;
			case "password":
				// Implicitely done already through oauth2.login event call.
				if (empty($userData[2])) {
					return Event::until("oauth2.error.json",array("","400:invalid_request"));
				}
				break;
			case "assertion":
				// Not implemented yet
				return Event::until("oauth2.error.json",array("","400:invalid_request"));
				break;
			case "refresh_token":
				if (empty($params['refresh_token'])) {
					return Event::until("oauth2.error.json",array("","400:invalid_request"));
				}
				$token = OAuth2\Models\Token::where("code","=",$params['refresh_token'])->where("type","=",2)->where("client_id","=",$client->id)->first();
				if (!$token) {
					return Event::until("oauth2.error.json",array("","400:invalid_request"));
				}
				$oldToken = OAuth2\Models\Token::where("refresh","=",$token->code)->first();
				
				// Increase the expiry of the old token
				switch (Config::get("oauth2-server::oauth2.mode","flexible")) {
					case "strict":
						$tokenScopes = 0;
						$totalScopes = 0;
						foreach ($oldToken->scopes()->get() as $v) {
							$totalScopes++;
							foreach ($requestedScopes as $vR) {
								if ($v->id == $vR->id) {
									$tokenScopes++;
									continue 2;
								}
							}
							// DAFUQ! Someone tried to cheat with the tokens!
							return Event::until("oauth2.error.json",array("","400:invalid_request"));
						}
						if ($tokenScopes != $totalScopes) return Event::until("oauth2.error.json",array("","400:invalid_request"));

						$oldToken->scopes()->delete();
						foreach ($requestedScopes as $rS) {
							$oldToken->scopes()->attach($rS->id);
						}
						$oldToken->expiry = time()+86400;
						$oldToken->save();
						return $oldToken->printToken();
						break;
					case "dynamic":
						$tokenScopes = 0;
						$totalScopes = 0;
						foreach ($oldToken->scopes()->get() as $v) {
							$totalScopes++;
							foreach ($requestedScopes as $vR) {
								if ($v->id == $vR->id) {
									$tokenScopes++;
									continue 2;
								}
							}
							// DAFUQ! Someone tried to cheat with the tokens!
							return Event::until("oauth2.error.json",array("","400:invalid_request"));
						}
						$oldToken->scopes()->delete();
						foreach ($requestedScopes as $rS) {
							$oldToken->scopes()->attach($rS->id);
						}
						$oldToken->expiry = time()+86400;
						$oldToken->save();
						return $oldToken->printToken();
						break;
					default:
						$oldToken->expiry = time()+86400;
						$oldToken->save();
						return $oldToken->printToken();
				}
				break;
			case "none":
				return Event::until("oauth2.error.json",array("","400:invalid_request"));
				break;
			default:
				return Event::until("oauth2.error.json",array("","400:invalid_request"));
		}
		// We have dealt with all the errors. Generate & return token...
		$t = OAuth2\Models\Token::generateAccessToken($client->id,$params['scope']);
		foreach ($requestedScopes as $rS) {
			$t->scopes()->attach($rS->id);
		}
		return $t->printToken();
// return Event::until("oauth2.redirect: access",array($params['redirect_uri'],$t));
	}
	private function getAuthorizationHeader() {
		if (array_key_exists("HTTP_AUTHORIZATION", $_SERVER))
			return $_SERVER["HTTP_AUTHORIZATION"];
		return false;
	}
	/* @return Client */
	public static function findClient($request) {
		$token = OAuth2\Models\Client::where("token","=",$request)->first();
		if ($token) return $token;
		else return false;
	}
	/* @return Response */
	public function finishClientAuthorization($p=array()) {
		if (empty($p['client_id'])) {
			return Event::until("oauth2.error.json",array("","400:invalid_request"));
		}
		$client = static::findClient($p['client_id']);
		if (!($client instanceof OAuth2\Models\Client)) {
			return Event::until("oauth2.error.json",array("","400:invalid_request"));
		}
		$params = $this->matchParams($client,$p);
		$r = $this->validateParams($client,$params);
		/* Basic checks */
		if ($r && $r instanceof stdClass) {
			return Event::until("oauth2.error: ".$r->type,array($r->uri,$r->code));
		}
		$rValid = Event::until("oauth2.generate: ".$params['response_type'],array($client,$params));
		if ($rValid && ($rValid instanceof OAuth2\Models\Token)) {
			return Event::until("oauth2.redirect: ".$params['response_type'], array($client,$params,$rValid));
		}
		else {
			return Event::until("oauth2.error.redirect",array($params['redirect_uri'],"unsupported_response_type"));
		}
	}
	/* @return Array if it worked, Reponse otherwise */
	public function getAuthorizeParams($p=array()) {
		if (empty($p['client_id'])) {
			return Event::until("oauth2.error.json",array("","400:invalid_request"));
		}
		$client = static::findClient($p['client_id']);
		if (!($client instanceof OAuth2\Models\Client)) {
			return Event::until("oauth2.error.json",array("","400:invalid_request"));
		}
		$params = $this->matchParams($client,$p);
		/* Make basic checks */
		$r = $this->validateParams($client,$params);
		if ($r && $r instanceof stdClass) {
			return Event::until("oauth2.error.".$r->type,array($r->uri,$r->code));
		}
		return $params;
	}
	/* @return array */
	protected function matchParams(OAuth2\Models\Client $client, $p=array()) {
		$params = $p;
		if (!empty($client->redirect)) {
			$validations['redirect_uri'] = "required|match:/^".preg_quote($client->redirect)."/";
			if (empty($params['redirect_uri'])) $params['redirect_uri'] = $client->redirect;
		}
		if (!isset($params['scope'])) $params['scope'] = $client->scope;
		return $params;
	}
	/* @return stdClass if an error came up */
	protected function validateParams(OAuth2\Models\Client $client, $params=array()) {
		$validations = array(
			"redirect_uri" => "required",
			"response_type" => "required|in:token,code,code-and-token",
			"redirect_uri" => "required|url|match:#^".preg_quote($client->redirect,"#")."#");
		$v = Validator::make($params,$validations);
		if ($v->fails()) {
			/* URI validation failed */
			if ($v->errors->has("redirect_uri")) {
				if (!empty($params['redirect_uri'])) {
					$r = new stdClass();
					$r->type = "redirect";
					$r->uri = $params['redirect_uri'];
					$r->code = "redirect_uri_mismatch";
					return $r;
				}
				else {
					$r = new stdClass();
					$r->type = "json";
					$r->uri = $params['redirect_uri'];
					$r->code = "400:redirect_uri_mismatch";
					return $r;
				}
			}
			/* Response type failed */
			if ($v->errors->has("response_type")) {
				$r = new stdClass();
				$r->type = "redirect";
				$r->uri = $params['redirect_uri'];
				$r->code = "unsupported_response_type";
				return $r;
			}
		}
		/* Scopes must be part of the client scopes, and must all exist */
		$requestedScopes = array();
		$scopes = explode(" ",$params['scope']);
		foreach ($scopes as $v) {
			$scopeName = urldecode($v);
			$s = OAuth2\Models\Scope::where("name","=",$scopeName)->first();
			if (!$s) {
				if (Config::get("oauth2-server::oauth2.new_scope","ignore") == "create") {
					$s = new OAuth2\Models\Scope();
					$s->name = $scopeName;
					$s->save();
					$client->scopes()->attach($s->id);
				}
				else {
					$r = new stdClass();
					$r->type = "redirect";
					$r->uri = $params['redirect_uri'];
					$r->code = "invalid_scope";
					return $r;
				}
			}
			$requestedScopes[] = $s;
		}
		$clientScopes = $client->scopes()->get();
		foreach ($requestedScopes as $v) {
			foreach ($clientScopes as $clientScope) {
				if ($v->id == $clientScope->id) {
					continue 2;
				}
			}
			$r = new stdClass();
			$r->type = "redirect";
			$r->uri = $params['redirect_uri'];
			$r->code = "invalid_scope";
			return $r;
		}
		return NULL;
	}
}
