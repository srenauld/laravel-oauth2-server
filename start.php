<?php
Autoloader::map(array(
	/* Required models. Feel free to mock those */
	"SebRenauld\OAuth2\Models\Scope" => dirname(__FILE__).DS."models".DS."scope.php",
	"SebRenauld\OAuth2\Models\Client" => dirname(__FILE__).DS."models".DS."client.php",
	"SebRenauld\OAuth2\Models\Token" => dirname(__FILE__).DS."models".DS."token.php",
	
	/* The actual library */
	"SebRenauld\OAuth2" => dirname(__FILE__).DS."libraries".DS."oauth2.php"
));
Route::filter("oauth2", function() {
	$t = Input::get("oauth_token");
	if (!$t) return Response::error(403);
	if (($r = SebRenauld\OAuth2::verify($t)) === false) {
		return Response::error(403);
	}
});
Event::listen("oauth2.error.json", function($r, $code) {
	$c = explode(":",$code);
	$msg = array_pop($c);
	if (count($c)) {
		$statusCode = array_pop($c);
	}
	else $statusCode = 200;
	return Response::json(json_encode(array('error' => $msg)),$statusCode);
});
Event::listen("oauth2.error.redirect", function($uri, $code) {
	$new_uri = $uri;
	if (!empty($code)) {
		if (stripos($new_uri,"?") !== false) {
			$new_uri .= "&error=".urlencode($code);
		}
		else {
			$new_uri .= "?error=".urlencode($code);
		}
	}
	return Redirect::to($new_uri);
});
Event::listen("oauth2.generate: token", function($client,$params) {
	// Scopes are considered safe here - they've been through Validate on all (default) paths.
	$requestedScopes = array();
	$scopes = explode(" ",$params['scope']);
	foreach ($scopes as $v) {
		$scopeName = urldecode($v);
		$s = SebRenauld\OAuth2\Models\Scope::where("name","=",$scopeName)->first();
		if (!$s) {
			$r = new stdClass();
			$r->type = "redirect";
			$r->uri = $params['redirect_uri'];
			$r->code = "invalid_scope";
			return $r;
		}
		$requestedScopes[] = $s;
	}
	$t = SebRenauld\OAuth2\Models\Token::generateAccessToken($client->id);
	foreach ($requestedScopes as $rS) {
		$t->scopes()->attach($rS->id);
	}
	return $t;
});
Event::listen("oauth2.redirect: token", function($client,$params,$code) {
	$new_uri = $params['redirect_uri'];
	if (!empty($code)) {
		if (stripos($new_uri,"#") !== false) {
			$new_uri .= "&";
		}
		else $new_uri .= "#";
		$new_uri .= "access_token=".urlencode($code->code)."&token_type=Bearer&expires_in=".($code->expiry-time());
	}
	return Redirect::to($new_uri);
});
Event::listen("oauth2.generate: code", function($client,$params) {
	$requestedScopes = array();
	$scopes = explode(" ",$params['scope']);
	foreach ($scopes as $v) {
		$scopeName = urldecode($v);
		$s = SebRenauld\OAuth2\Models\Scope::where("name","=",$scopeName)->first();
		if (!$s) {
			$r = new stdClass();
			$r->type = "redirect";
			$r->uri = $params['redirect_uri'];
			$r->code = "invalid_scope";
			return $r;
		}
		$requestedScopes[] = $s;
	}
	$t = SebRenauld\OAuth2\Models\Token::generateAuthToken($client->id);
	foreach ($requestedScopes as $rS) {
		$t->scopes()->attach($rS->id);
	}
	return $t;
});

Event::listen("oauth2.redirect: code", function($client,$params,$code) {
	$new_uri = $params['redirect_uri'];
	if (!empty($code)) {
		if (stripos($new_uri,"?") !== false) {
			$new_uri .= "&code=".urlencode($code->code);
		}
		else $new_uri .= "?code=".urlencode($code->code);
	}
	return Redirect::to($new_uri);
});
Event::listen("oauth2.login.id", function($client_id, $client_secret) {
	$ct = SebRenauld\OAuth2\Models\Client::where("token","=",$client_id)->where("secret","=",$client_secret)->first();
	if ($ct) return $ct;
	return false;
});
Event::listen("oauth2.login.username", function($un,$password) {
	// Using the default Auth implementation
	$username = Config::get("auth.username");
	$model = Config::get("auth.model");
	$m = new $model();
	$c = $m->where($username,"=",$un)->first();
	if ($c && Hash::check($password, $c->password)) {
		$ct = SebRenauld\OAuth2\Models\Client::where("user_id","=",$c->id)->first();
		if ($ct) return $ct;
	}
	return false;
});
