<?php

require_once(dirname(__DIR__) . '/LatexTools.php');

$latexTools = new LatexTools();
$latexTools->renderIntoResponse('(\frac{\beta }{\mu})^\beta {\Gamma(\beta )} \,  e^{-\frac{V\,\beta }{\mu }} \label{gamma}');

