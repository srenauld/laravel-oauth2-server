<?php

class Oauth2_Sp_Install {

	/**
	 * Make changes to the database.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create("oa_tokens", function($table) {
			$table->increments("id")->unsigned();
			$table->integer("type")->unsigned();
			$table->integer("client_id")->unsigned();
			$table->string("code",40);
			$table->string("refresh",40);
			$table->integer("expiry")->unsigned();
			$table->timestamps();
			
			$table->unique(array("type","code"));
			$table->index("refresh");
			$table->index("client_id");
			$table->index("code");
			$table->foreign("refresh")->references("code")->on("oa_tokens")->on_update("cascade");
		});
		Schema::create("oa_clients", function($table) {
			$table->increments("id")->unsigned();
			$table->integer("user_id")->unsigned()->nullable();
			$table->string("redirect",255);
			$table->string("token",40);
			$table->string("secret",40);
			$table->timestamps();
			
			$table->unique(array("token","secret"));
			$table->index("user_id");
			
		});
		Schema::create("oa_scopes",function($table) {
			$table->increments("id")->unsigned();
			$table->string("name",255);
			$table->timestamps();
			
			$table->unique("name");
		});
		Schema::create("oa_client_scope", function($table) {
			$table->increments("id")->unsigned();
			$table->integer("client_id")->unsigned();
			$table->integer("scope_id")->unsigned();
			$table->timestamps();
			
			$table->unique(array("client_id","scope_id"));
			
			$table->foreign("client_id")->references("id")->on("oa_clients")->on_delete("cascade")->on_update("cascade");
			$table->foreign("scope_id")->references("id")->on("oa_scopes")->on_delete("cascade")->on_update("cascade");
		});
		Schema::create("oa_token_scope", function($table) {
			$table->increments("id")->unsigned();
			$table->integer("token_id")->unsigned();
			$table->integer("scope_id")->unsigned();
			$table->timestamps();
			
			$table->unique(array("token_id","scope_id"));
			
			$table->foreign("token_id")->references("id")->on("oa_tokens")->on_delete("cascade")->on_update("cascade");
			$table->foreign("scope_id")->references("id")->on("oa_scopes")->on_delete("cascade")->on_update("cascade");
		});
		//
	}

	/**
	 * Revert the changes to the database.
	 *
	 * @return void
	 */
	public function down()
	{
		//
	}

}
