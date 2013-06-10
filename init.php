<?php

/**
 * Use PSR-0 autoloader to load compatible code from the vendor directory.
 */
spl_autoload_register(
	function ($class)
	{
		$directories = array(
			'vendor/zendframework',
		);

		foreach ($directories as $directory)
		{
			if (Kohana::auto_load($class, $directory))
				return;
		}
	}
);