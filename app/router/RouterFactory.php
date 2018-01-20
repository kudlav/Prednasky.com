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
		$router[] = new Route('[<locale=cs cs|en>/]<presenter>/<action>[/<id>]',[
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

		return $router;
	}
}
