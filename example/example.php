<?php

require_once __DIR__.'/../vendor/autoload.php';

$config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents(__DIR__.'/../config.yml'));

$chartService = new \TradingView\ChartService($config);

//PLEASE UPDATE WITH json dump data made in snapshot request in trading view widget
$data = '{"layout":"s","hidpiRatio":1.25,"charts":[{"panes":[{"leftAxis":{"content":"data:,","contentWidth":0,"contentHeight":507},"rightAxis":{"content":"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADk...';

echo sprintf('<a href="http://%s">Picture</a>', $chartService->save($data));
