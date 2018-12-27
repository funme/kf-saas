<?php

namespace Mahuyang\KfSaas;

use App\Caches\UserCache;
use App\Models\User;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

class SaasUserProvider implements UserProvider {
	/**
	 * The login users.
	 *
	 * @var \Illuminate\Contracts\Auth\Authenticatable|null
	 */
	protected $user;

	/**
	 * Retrieve a user by their unique identifier.
	 *
	 * @param  mixed $identifier
	 *
	 * @return \Illuminate\Contracts\Auth\Authenticatable|null
	 */
	public function retrieveById( $identifier ) {
		$user = UserCache::init( $identifier )->get();

		return $user ? $this->getGenericUser( json_decode( $user ) ) : null;
	}

	/**
	 * Retrieve a user by their unique identifier and "remember me" token.
	 *
	 * @param  mixed $identifier
	 * @param  string $token
	 *
	 * @return \Illuminate\Contracts\Auth\Authenticatable|null
	 */
	public function retrieveByToken( $identifier, $token ) {
		list( $id6d, $company_id ) = explode( '.', $identifier );
		$model = User::factory( compact( 'id6d', 'company_id' ), false );

		if ( ! $model->exists ) {
			return null;
		}

		$rememberToken = $model->getRememberToken();

		return $rememberToken && hash_equals( $rememberToken, $token ) ? $this->getGenericUser( json_decode( UserCache::init( $identifier )->get() ) ) : null;
	}

	/**
	 * Update the "remember me" token for the given user in storage.
	 *
	 * @param  \Illuminate\Contracts\Auth\Authenticatable $user
	 * @param  string $token
	 *
	 * @return void
	 */
	public function updateRememberToken( UserContract $user, $token ) {
		$identifier = $user->id;
		list( $id6d, $company_id ) = explode( '.', $identifier );
		$user = User::factory( compact( 'id6d', 'company_id' ) );
		$user->setRememberToken( $token );

		$timestamps = $user->timestamps;

		$user->timestamps = false;

		$user->save();

		$user->timestamps = $timestamps;
	}

	/**
	 * Retrieve a user by the given credentials.
	 *
	 * @param  array $credentials
	 *
	 * @return \Illuminate\Contracts\Auth\Authenticatable|null
	 */
	public function retrieveByCredentials( array $credentials ) {
		/*
		 * 201：验证通过
		 * 403：连续三次登录错误，请在3分钟后重试
		 * 404：账号错误
		 * 406：密码错误
		 * 407：令牌错误
		 */
		$result = ( new AccountLogin() )->login( $credentials['email'], $credentials['password'] );
		if ( ! empty( $result['server_response'] ) && $result['server_response']['status_code'] != 201 ) {
			return $this->getGenericUser( $result['server_response'] );
		} else {
			$id6d                                 = $result['server_response']['id6d'];
			$company_id                           = $result['server_response']['company_id'];
			$worker                               = ( new Worker() )->worker( $id6d, $company_id );
			$worker['server_response']['id']      = $id6d . '.' . $company_id;
			$worker['server_response']['company'] = $result['server_response'];

			return $this->getGenericUser( $worker['server_response'] );
		}
	}

	/**
	 * Get the generic user.
	 *
	 * @param  mixed $user
	 *
	 * @return \Mahuyang\KfSaas\GenericUser|null
	 */
	protected function getGenericUser( $user ) {
		if ( ! is_null( $user ) ) {
			return new GenericUser( (array) $user );
		}
	}

	/**
	 * Validate a user against the given credentials.
	 *
	 * @param  \Illuminate\Contracts\Auth\Authenticatable $user
	 * @param  array $credentials
	 *
	 * @return bool
	 */
	public function validateCredentials( UserContract $user, array $credentials ) {
		$this->user = $user;
		if ( isset( $user->status_code ) && $user->status_code == 201 ) {
			$identifier = $user->id;
			UserCache::init( $identifier )->set( json_encode( $user->toArray() ) );

			return true;
		}

		return false;
	}

	/**
	 * Retrieve a user by the given saas token.
	 *
	 * @param  string token
	 *
	 * @return \Illuminate\Contracts\Auth\Authenticatable|null
	 */
	public function retrieveBySaasToken( $token ) {
		$result = ( new TokenLogin() )->login( $token );
		if ( $result['server_response'] && $result['server_response']['status_code'] == 201 ) {
			$id6d                                 = $result['server_response']['id6d'];
			$company_id                           = $result['server_response']['company_id'];
			$worker                               = ( new Worker() )->worker( $id6d, $company_id );
			$worker['server_response']['id']      = $id6d . '.' . $company_id;
			$worker['server_response']['company'] = $result['server_response'];

			return $this->getGenericUser( $worker['server_response'] );
		}
	}
}
