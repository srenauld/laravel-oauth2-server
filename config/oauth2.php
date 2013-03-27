<?php
return array(
		/* What to do with scope forgery?
		 * 
		 * In the three-legged (GET_authorize -> POST_authorize -> POST_token) flow, the scope can (and should!) be set
		 * in two of the three requests:
		 * - GET_authorize
		 * - POST_token
		 * This configuration setting allows you to define what the server will respond if the scopes do not match.
		 * 
		 * This config setting takes a value from a set of three:
		 * - "replicate" will casually ignore the scope given on POST_token and simply copy the scope from the auth token to the access token on an authorization_code request
		 * and on a refresh_code request. Effectively, the scope parameter is now optional on token request
		 * - "dynamic" will parse the scope and allocate scopes to the new token based on a subset of the first one. This can allow you to restrict the scope betzeen requests, but NEVER to extend it.
		 * - "strict" will throw an error if the two scopes do not have exactly the same components (order is irrelevant)
		 * Defaults to "replicate" if unspecified or invalid.
		 */
		 
		"mode" => "replicate",
		/* What to do when an unknown scope is sent?
		 * You can dynamically create scopes by setting this to "create".
		 * Setting it to "ignore" will throw an error whenever an unknown scope is declared.
		 *
		 * Defaults to "ignore"
		 */
		"new_scope" => "create",
		/* Default auth token lifetime
		 * integers, please */
		"auth_lifetime" => 60,
		
		/* Default access token lifetime
		 * Again, integers, please */
		"access_lifetime" => 86400
);
	