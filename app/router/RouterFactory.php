<?php
declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;


class RouterFactory
{
	use Nette\StaticClass;


	public static function createRouter(): RouteList
	{
		$router = new RouteList;

		// Admin module

		$router[] = new Route('admin/[<locale=cs cs|en>/]<presenter>/<action>[/<id>]',[
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

		$router[] = new Route('cosign/valid', array(
			'module' => 'Front',
			'presenter' => 'Sign',
			'action' => 'cosignValid'
		), Route::ONE_WAY);

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

		$router[] = new Route('[<locale=cs cs|en>/]video/<id [0-9]+>', 'Front:Video:default');

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
