<?php

require_once(dirname(__DIR__) . '/src/LatexTools.php');

$latexTools = new LatexTools();
$latexTools->renderIntoResponse('(\frac{\beta }{\mu})^\beta {\Gamma(\beta )} \,  e^{-\frac{V\,\beta }{\mu }} \label{gamma}');

