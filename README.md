laravel-oauth2-server
=====================

A drop-in oauth2 (draft 31) authentication provider for the Laravel framework.

Purpose
=====================
To provide a drop-in, easy-to-use, Laravelized OAuth2 provider (=server). The current alternatives are direct rip-offs of the code samples present at http://oauth.net/2/ and do not fully leverage Laravel's incredible potential. In addition to this, they only go as far as draft 10.

As a result, it just made sense to rewrite, trim and reorganize some of their code, closely following draft31 of the IETF RFC for OAuth2, and to encapsulate the endpoints in a set of routes and a filter ( (:bundle)/token and (:bundle)/authorize).
As many of the features of the draft are supported, and are listed below:
* Grant types
  - [x] `authorization_code`
  - [x] `username` (vs. client_id client_secret pair)
  - [x] `refresh_token`
  - [ ] `assertion` (unclear SAML2.0 format as of now, and no major oAuth supplier seems to provide it)
* Response type
  - [x] `token`
  - [x] `code`
  - [ ] `code-and-token` (don't see the point + not defined in section 11.3 of draft31. Will implement if there are enough requests for it)
* [x] Scopes
  - [x] Scope restriction client->token (the client's scope assignments define the max scope he/she can request for a token)
* [x] Client
* [ ] Client-Auth link (partial. Define your client as having user_id to the Auth ID you'd like, and modify the filter. I'll provide an example)

So, overall, it is pretty feature-rich, but can still be extended.

Usage
======================

Installation
----------------------
Install it by using artisan (`php artisan bundle:install oauth2-sp`) or by manually copying the contents of this repository into `bundles/`.
This bundle requires tables, and a migration is provided. Run `php artisan migrate oauth2-server` to create the tables - the following will be created: `oa_clients`, `oa_tokens`, `oa_scopes` along with client->scope, token->scope associative tables.

Loading the bundle
----------------------
Add the following to your bundles.php file:
   ```"oauth2-server" => array("auto" => true, "handles" => "oa"),```
`auto` will auto-start the bundle on every request, allowing you to gain access to the filter defined in `start.php` along with the autoloader definitions in it. `handles` will allow you to define a bundle sub-directory mapping (in my case /oa/) for your endpoint config. The endpoints token and authorized will be present in it.

Once this is done, you're all done!

Adding a client
----------------------
Adding a client is done through Laravel models, for ease of use. As follows:
   ```php
   $client = new SebRenauld\OAuth2\Models\Client();
   $client->redirect = "http://my.redirect.domain/";
   $client->token = SebRenauld\OAuth2\Models\Token::generateEntropy();
   $client->secret = SebRenauld\OAuth2\Models\Token::generateEntropy();
   // $client->user_id = Auth::user()->id;
   $client->save();
   ```
   
In order of appearance:
- `redirect` provides the root redirect URL for the client. Tokens can be requested for subdomains if required without having to create new clients.
- `token` and `secret` provide your client_id and client_secret parameters
- `user_id` gives you one additional field to play with if you would like to easily build a relational mapping with your Auth DB

Saving it creates the client.

Creating scopes
--------------------------
Creating a scope is also a matter of models. As follows:
  ```php
  $scope = new SebRenauld\OAuth2\Models\Scope();
  $scope->name = "my_scope";
  $scope->save();
  ```

`name` is, evidently, your scope name.

Binding scopes to a client
--------------------------
Adding possible scopes to a client (beyond configuration option auto-creation) is also extremely straightforward. Find the client and the scope(here, assumed `$client` and `$scope`) and simply attach one to the other:
  ```php
  $client->scopes()->attach($scope->id);
  ```
This also works in exactly the same fashion for tokens!

Deleting a scope from a client also follows the Laravel Eloquent model system. However, bear in mind that this will not delete scopes from tokens assigned to the client, merely what is created in the future (for consistency).

Using the provider
==========================
What would an OAuth provider be without an example? Requesting a token is pretty straightforward. Create a client with a token and secret and a redirect URI. If you would like to automate the process, use Google's OAuth Playground ( https://developers.google.com/oauthplayground/ - a way to do this is visible at http://www.youtube.com/watch?v=xzmEC_a-S9M ). 

Three-legged OAuth2
--------------------------
Step 1: request an auth token
--------------------------
Request:
```/authorize?response_type=code&client_id=[YOUR ID]&redirect_uri=[YOUR REDIRECT URI]&scope=[YOUR SCOPE]```
Returns:
* On success, redirects to your redirect URI with a `code` in the URI.
* On failure, redirects to your redirect URI with the error code in the URI. If the URI is mismatched, JSON-prints it instead

Step 2: Exchange that token for an access token
-----------------------------
Request:
`/token?client_id=[YOUR ID]&client_secret=[YOUR SECRET]&grant_type=authorization_code&scope=[YOUR SCOPE]&code=[YOUR CODE]&redirect_uri=[YOUR REDIRECT URI]`
Returns:
* On success, a JSON object containing the type of token, an access token, its expiration info, and a refresh token
* On failure, a JSON object containing the error

Step 3 (optional): Refresh the token
-----------------------------
Request:
`/token?client_id=[YOUR ID]&client_secret=[YOUR SECRET]&grant_type=refresh_token&scope=[YOUR SCOPE]&refresh_token=[YOUR REFRESH CODE]&redirect_uri=[YOUR REDIRECT URI]`
Returns:
* Same as above

Laravel Filter: oauth2
============================
The filter for this library is called `oauth2`. If the user has not supplied a token, it will default to Response::error(403).

You can easily modify it in `start.php` or make your own. Note that `verify($tokenStr)` returns the CLIENT, not the token, for Auth purposes.

Extending this library
=============================
You can easily define more response_types for step 1 by listening to events. This library will fire the event `oauth2.generate: [TYPE]` and wait for the first token/response return. Redirections are done with `oauth2.redirect: [TYPE]` if a token has been acquired.

A more exhaustive list of events:
* `oauth2.error.redirect` handles redirections when an error has happened
* `oauth2.error.json` handles JSON-output errors

These allow you to easily modify the return format of the library.
