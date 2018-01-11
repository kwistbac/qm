<?php
$application = new \Symfony\Component\Console\Application();

// Register commands
foreach (glob(__DIR__ . "/../src/Command/*Command.php") as $filename) {
    $className = "\\Qm\\Command\\" . basename($filename, '.php');
    $application->add(new $className());
}

$application->run();
