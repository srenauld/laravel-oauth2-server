<?php
class OAuth_OAuth_Controller extends Base_Controller {
	public $restful = true;
	public function get_authorize() {
		$oA = new SebRenauld\OAuth2();
		$i = Input::all();
		$r = $oA->getAuthorizeParams($i);
		var_dump($r);
		die();
	}
	public function post_authorize() {
	}
	public function post_token() {
		$oA = new SebRenauld\OAuth2();
		$r = $oA->grantAccessToken(Input::all());
		if ($r instanceof Laravel\Response) {
			return $r;
		}
		var_dump($r);
		die();
	}
	public function get_token() {
		return $this->post_token();
	}
}
