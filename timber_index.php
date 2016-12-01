<?php

$template = $GLOBALS['timber_extended_template'];
if (empty($template)) {
  echo 'No template file could be located, does your theme provide twig files?';
  exit;
}
$context = Timber::get_context();
Timber::render($template, $context);
