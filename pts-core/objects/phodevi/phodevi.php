<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2009, Phoronix Media
	Copyright (C) 2009, Michael Larabel
	phodevi.php: The object for interacting with the PTS device framework

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

define("PHODEVI_AVOID_CACHE", 0); // No caching
define("PHODEVI_STAND_CACHE", 1); // Standard caching
define("PHODEVI_SMART_CACHE", 2); // Smart caching

class phodevi
{
	static $device_cache = null;
	static $smart_cache = null;

	public static function read_name($device)
	{
		return phodevi::read_property($device, "identifier");
	}
	public static function read_sensor($device, $read_sensor)
	{
		$value = false;

		if(method_exists("phodevi_" . $device, "read_sensor"))
		{
			eval("\$sensor_function = phodevi_" . $device . "::read_sensor(\$read_sensor);");

			if(is_array($sensor_function))
			{
				if(count($sensor_function) > 1)
				{
					// TODO: support passing more than one argument
					$sensor_function_pass = $sensor_function[1];
				}
				$sensor_function = $sensor_function[0];
			}
			else
			{
				$sensor_function_pass = null;
			}

			if(method_exists("phodevi_" . $device, $sensor_function))
			{
				eval("\$read_value = phodevi_" . $device . "::" . $sensor_function . "(\$sensor_function_pass);");

				$value = $read_value; // possibly add some sort of check here
			}
		}

		return $value;
	}
	public static function read_property($device, $read_property)
	{
		$value = false;

		if(method_exists("phodevi_" . $device, "read_property"))
		{
			eval("\$property = phodevi_" . $device . "::read_property(\$read_property);");

			$cache_code = $property->cache_code();

			if($cache_code != PHODEVI_AVOID_CACHE && isset(self::$device_cache[$device][$read_property]))
			{
				$value = self::$device_cache[$device][$read_property];
			}
			else
			{
				$dev_function = $property->get_device_function();

				if(is_array($dev_function))
				{
					if(count($dev_function) > 1)
					{
						// TODO: support passing more than one argument
						$dev_function_pass = $dev_function[1];
					}

					$dev_function = $dev_function[0];
				}
				else
				{
					$dev_function_pass = null;
				}

				if(method_exists("phodevi_" . $device, $dev_function))
				{

					eval("\$read_value = phodevi_" . $device . "::" . $dev_function . "(\$dev_function_pass);");

					$value = $read_value; // possibly add some sort of check here

					if($cache_code != PHODEVI_AVOID_CACHE)
					{
						self::$device_cache[$device][$read_property] = $value;

						if($cache_code == PHODEVI_SMART_CACHE)
						{
							// TODO: For now just copy the smart cache to other var, but come up with better yet efficient way
							self::$smart_cache[$device][$read_property] = $value;
						}
					}
				}
			}
		}

		return $value;
	}
	public static function initial_setup()
	{
		// Operating System Detection
		$supported_operating_systems = array("Linux", array("Solaris", "Sun"), "BSD", array("MacOSX", "Darwin"));
		$uname_s = strtolower(php_uname("s"));

		foreach($supported_operating_systems as $os_check)
		{
			if(!is_array($os_check))
			{
				$os_check = array($os_check);
			}

			$is_os = false;
			$os_title = $os_check[0];

			for($i = 0; $i < count($os_check) && !$is_os; $i++)
			{
				if(strpos($uname_s, strtolower($os_check[$i])) !== false) // Check for OS
				{
					define("OPERATING_SYSTEM", $os_title);
					define("IS_" . strtoupper($os_title), true);
					$is_os = true;
				}
			}

			if(!$is_os)
			{
				define("IS_" . strtoupper($os_title), false);
			}
		}

		if(!defined("OPERATING_SYSTEM"))
		{
			define("OPERATING_SYSTEM", "Unknown");
			define("IS_UNKNOWN", true);
		}
		else
		{
			define("IS_UNKNOWN", false);
		}

		define("OS_PREFIX", strtolower(OPERATING_SYSTEM) . "_");

		// OpenGL / graphics detection
		$graphics_detection = array("NVIDIA", array("ATI", "fglrx"), "Mesa");
		$opengl_driver = phodevi::read_property("system", "opengl-driver") . " " . phodevi::read_property("system", "dri-display-driver");
		$found_gpu_match = false;

		foreach($graphics_detection as $gpu_check)
		{
			if(!is_array($gpu_check))
			{
				$gpu_check = array($gpu_check);
			}

			$is_this = false;
			$gpu_title = $gpu_check[0];

			for($i = 0; $i < count($gpu_check) && !$is_this; $i++)
			{
				if(stripos($opengl_driver, $gpu_check[$i]) !== false) // Check for GPU
				{
					define("IS_" . strtoupper($gpu_title) . "_GRAPHICS", true);
					$is_this = true;
					$found_gpu_match = true;
				}
			}

			if(!$is_this)
			{
				define("IS_" . strtoupper($gpu_title) . "_GRAPHICS", false);
			}
		}

		define("IS_UNKNOWN_GRAPHICS", ($found_gpu_match == false));
	}
	public static function restore_smart_cache($restore_dir, $client_version = 0)
	{
		if(is_file($restore_dir . "phodevi.cache"))
		{
			$restore_cache = unserialize(file_get_contents($restore_dir . "phodevi.cache"));

			if($restore_cache instanceOf phodevi_cache)
			{
				self::$device_cache = $restore_cache->restore_cache($restore_dir, $client_version);
			}
		}
	}
	public static function create_smart_cache($store_dir, $client_version = 0)
	{
		if(!empty(self::$smart_cache) && is_writable($store_dir))
		{
			file_put_contents($store_dir . "phodevi.cache", 
				serialize(new phodevi_cache(self::$smart_cache, $store_dir, $client_version)));
		}
	}
}

?>
