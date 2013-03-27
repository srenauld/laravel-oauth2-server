<?php
namespace SebRenauld\OAuth2\Models;
use Eloquent;
class Scope extends Eloquent {
	public static $table = "oa_scopes";
	public static function listScopes() {
		$r = array();
		foreach (static::get() as $v) {
			$r[] = $v->name;
		}
		return $r;
	}
}