<?php

function autoload($class)
{
	$path = __DIR__.'/';

	if (($namespace = strrpos($class = ltrim($class, '\\'), '\\')) !== false)
		$path .= strtr(substr($class, 0, ++$namespace), '\\', '/');

	require($path . strtr(substr($class, $namespace), '_', '/') . '.php');
}

spl_autoload_register('autoload');

?>
