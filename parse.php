<?php

function calculateMedian(array $values): float|int
	{
	sort($values);
	$count = count($values);

	if ($count % 2 === 0)
		{
		$median = ($values[($count / 2) - 1] + $values[$count / 2]) / 2;
		}
	else
		{
		$median = $values[(int)floor($count / 2)];
		}

	return $median;
	}

function matchLogItem(array &$routes, array $logItem): void
	{
	foreach ($routes as &$route)
		{
		if ($route['method'] === $logItem['method']
			&& preg_match($route['pathRegex'], $logItem['path']) === 1)
			{
			$route['ttfb'][] = $logItem['ttfb'] * 1000; // sec to ms
			$route['ttlb'][] = $logItem['ttlb'] / 1000; // µs to ms
			$route['hits']++;
			}
		}
	}

function readFileAsync($fileName): Generator
	{
	$handle = fopen($fileName, 'rb');

	while (!feof($handle))
		{
		yield fgets($handle);
		}

	fclose($handle);
	}

if (empty($argv[1]))
	{
	throw new InvalidArgumentException('Argument #1 should be a file path to Routes JSON file');
	}

$routesFilePath = $argv[1];
$routesJson     = file_get_contents($routesFilePath);
/** @noinspection PhpUnhandledExceptionInspection */
$routes = json_decode($routesJson, true, 512, JSON_THROW_ON_ERROR);
//printf('Found %d routes.', count($routes));

// Padding
foreach ($routes as $index => $route)
	{
	$routes[$index]['ttfb'] = [];
	$routes[$index]['ttlb'] = [];
	$routes[$index]['hits'] = 0;
	}

if (empty($argv[2]))
	{
	throw new InvalidArgumentException('Argument #2 should be a file path to Varnish Log LD-JSON file');
	}
$varnishLogFilePath = $argv[2];
$lineCounter        = 0;
$lines              = readFileAsync($varnishLogFilePath);
foreach ($lines as $line)
	{
	try
		{
		$logItem = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
		matchLogItem($routes, $logItem);
		}
	catch (JsonException)
		{
		}
	$lineCounter++;
	}
//printf('Parsed %d log lines.', $lineCounter);

foreach ($routes as &$route)
	{
	// De-clutter
	unset($route['pathRegex']);
	// Decorate
	$route['ttfb'] = !empty($route['ttfb']) ? number_format(calculateMedian($route['ttfb']), 2, '.', '') : 0;
	$route['ttlb'] = !empty($route['ttlb']) ? number_format(calculateMedian($route['ttlb']), 2, '.', '') : 0;
	}
unset($route);

/** @noinspection PhpUnhandledExceptionInspection */
echo json_encode($routes, JSON_THROW_ON_ERROR);