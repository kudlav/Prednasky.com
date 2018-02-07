<?php

namespace App;

use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;


class RouterFactory
{
	use Nette\StaticClass;

	/**
	 * @return Nette\Application\IRouter
	 */
	public static function createRouter()
	{
		$router = new RouteList;

		// Admin module

		$router[] = new Route('[<locale=cs cs|en>/]/admin/<presenter>/<action>[/<id>]',[
			'module' => 'Admin',
			'presenter' => 'Homepage',
			'action' => 'default',
			'id' => null,
			'locale' => [
				Route::FILTER_TABLE => [
					'cz' => 'cs_CZ',
					'en' => 'en_GB'
				]
			]
		]);

		// Front module

		$router[] = new Route('[<locale=cs cs|en>/]ls/<path>',[
			'module' => 'Front',
			'presenter' => 'Homepage',
			'action' => 'default',
			'path' => [
				Route::PATTERN => ".*",
			],
			'locale' => [
				Route::FILTER_TABLE => [
					'cz' => 'cs_CZ',
					'en' => 'en_GB'
				]
			]
		]);

		$router[] = new Route('[<locale=cs cs|en>/]<presenter>/<action>[/<id>]',[
			'module' => 'Front',
			'presenter' => 'Homepage',
			'action' => 'default',
			'locale' => [
				Route::FILTER_TABLE => [
					'cz' => 'cs_CZ',
					'en' => 'en_GB'
				]
			]
		]);

		return $router;
	}
}
